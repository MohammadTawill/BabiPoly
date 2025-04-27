<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use setasign\Fpdi\Fpdi;
use Intervention\Image\Facades\Image;

class PdfController extends Controller
{
    /**
     * Fetch Instagram bio using GPT-4 API.
     */
    private function fetchInstagramBioFromGPT($username, $profileImageUrl, $wordLimit = 40)
    {
        // OpenAI API Key
        $apiKey = env('OPEN_AI_TOKEN');
    
        // Messages for GPT-4
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that generates short and engaging descriptions for Instagram accounts.',
            ],
            [
                'role' => 'user',
                'content' => "Write a short and engaging description about the Instagram account with the username '$username'. "
                    . "The profile picture URL is: $profileImageUrl. "
                    . ($wordLimit ? "Limit the description to a maximum of $wordLimit words." : "Generate a friendly and creative description."),
            ],
        ];
    
        // Make a request to OpenAI API
        $response = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4', // Use GPT-4 model
            'messages' => $messages,
            'max_tokens' => 100, // Adjust token limit as needed
            'temperature' => 0.7, // Adjust creativity level
        ]);
    
        // Check for errors
        if ($response->failed()) {
            throw new \Exception('Failed to fetch data from OpenAI: ' . $response->body());
        }
    
        // Get the generated text
        $bio = trim($response->json('choices.0.message.content'));
    
        // Remove unwanted text (e.g., emojis, special characters)
        $bio = preg_replace('/[^\w\s.,!?\'"-]/u', '', $bio); // Removes emojis and special characters
    
        // Trim again to clean up any extra spaces
        return trim($bio);
    }

    /**
     * Generate a PDF with Instagram profile details.
     */
    public function generatePdf(Request $request)
    {
        // Redirect to the form page if the request method is GET
        if ($request->isMethod('get')) {
            return redirect('/');
        }

        // Get the Instagram link from the request
        $instagramUrl = $request->input('instagram_link');

        if (!$instagramUrl) {
            return redirect('/')->with('error', 'Instagram link is required');
        }

        try {
            // Extract the username from the Instagram URL
            $parsedUrl = parse_url($instagramUrl);
            $path = $parsedUrl['path'] ?? '';
            $segments = explode('/', trim($path, '/'));
            $profileName = $segments[0] ?? null; // The first segment is the username

            if (!$profileName) {
                throw new \Exception('Invalid Instagram URL. Unable to extract username.');
            }

            // Fetch the HTML content of the Instagram profile page
            $client = new \GuzzleHttp\Client();
            $response = $client->get($instagramUrl);
            $html = (string) $response->getBody();

            // Use Symfony DomCrawler to parse the HTML and extract the profile image
            $crawler = new Crawler($html);
            $profileImage = $crawler->filter('meta[property="og:image"]')->attr('content');

            if (!$profileImage) {
                throw new \Exception('Unable to fetch profile image from the Instagram page.');
            }

            $logoContent = file_get_contents($profileImage);

            // Path to the local PDF template
            $pdfTemplatePath = public_path('files/Babipoly.pdf');

            if (!file_exists($pdfTemplatePath)) {
                return redirect('/')->with('error', 'PDF template not found');
            }

            // Create a new FPDI instance
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($pdfTemplatePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage('L', [$size['height'], $size['width']]);
                $pdf->useTemplate($templateId);

                // Add the logo to specific pages (1, 3, 4, 5, 8)
                if (in_array($pageNo, [1, 3, 4, 5, 8])) {
                    // Detect the MIME type of the image
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($logoContent);

                    // Determine the correct file extension based on the MIME type
                    $extension = match ($mimeType) {
                        'image/jpeg' => '.jpg',
                        'image/png' => '.png',
                        'image/gif' => '.gif',
                        default => null,
                    };

                    // Ensure the image is in a supported format
                    if ($extension === null) {
                        throw new \Exception('Unsupported image format: ' . $mimeType);
                    }

                    // Save the logo with the correct extension
                    $logoPath = sys_get_temp_dir() . '/logo' . $extension;
                    file_put_contents($logoPath, $logoContent);

                    // Add the logo to the PDF
                    if ($pageNo === 1) {
                        $pdf->Image($logoPath, 210, 50, 80, 80); //(x, y, width, height)
                    } elseif ($pageNo === 3) {
                        $pdf->Image($logoPath, 40, 23, 70, 70); //(x, y, width, height)
                    } elseif ($pageNo === 4) {
                        $pdf->Image($logoPath, 119.4, 162, 7, 7); // First image
                        $pdf->Image($logoPath, 193, 87, 28, 28); // Second image

                        // Add the profileName to page 4 (first position)
                        $pdf->SetFont('Arial', 'B', 4); // Use bold Arial font with size 12
                        $pdf->SetXY(117, 155.5); // Adjust position (x, y) for the first text
                        $pdf->Cell(0, 10, $profileName); // Add the profileName

                        // Add the profileName to page 4 (second position)
                        $pdf->SetFont('Arial', 'B', 12); // Use bold Arial font with size 12
                        $pdf->SetXY(192, 78); // Adjust position (x, y) for the second text
                        $pdf->Cell(0, 10, $profileName); // Add the profileName again                     
                    } elseif ($pageNo === 5) {
                        $pdf->Image($logoPath, 238, 25, 22, 22); // First image
                        $pdf->Image($logoPath, 200, 128, 13, 13); // Second image
                        $pdf->Image($logoPath, 280, 128, 13, 13); // Third image
                    } else {
                        $pdf->Image($logoPath, 175, 81, 25, 25); // Adjust position (x, y) and size (width, height)
                    }

                    // Clean up the temporary file
                    unlink($logoPath);
                }

                // Add the profile name and bio to the fifth page
                if ($pageNo === 5) {
                    // Fetch three different texts from GPT-4
                    $bio1 = $this->fetchInstagramBioFromGPT($profileName, $profileImage);
                    $bio2 = $this->fetchInstagramBioFromGPT($profileName, $profileImage); // Second text
                    $bio3 = $this->fetchInstagramBioFromGPT($profileName, $profileImage); // Third text
                
                    // Set font and position for the first text
                    $pdf->SetFont('Arial', '', 12); // Use Arial with a smaller font size
                    $pdf->SetXY(173, 50); // Adjust position (x, y) for the first text
                    $textWidth = 150; // Width for the text box
                    $lineHeight = 9; // Line height for the text
                    $pdf->MultiCell($textWidth, $lineHeight, $bio1); // Add the first text
                
                    // Set position for the second text
                    $pdf->SetFont('Arial', '', 7); // Use Arial with a smaller font size
                    $pdf->SetXY(173, 143); // Adjust position (x, y) for the second text
                    $textWidth = 70; // Width for the text box
                    $lineHeight = 3; // Line height for the text
                    $pdf->MultiCell($textWidth, $lineHeight, $bio2); // Add the second text
                
                    // Set position for the third text
                    $pdf->SetFont('Arial', '', 8); // Use Arial with a smaller font size
                    $pdf->SetXY(253.5, 143); // Adjust position (x, y) for the third text
                    $textWidth = 70; // Width for the text box
                    $lineHeight = 3; // Line height for the text
                    $pdf->MultiCell($textWidth, $lineHeight, $bio3); // Add the third text
                }
            }

            // Output the modified PDF
            $pdfContent = $pdf->Output('S'); // 'S' returns the PDF as a string
            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="modified.pdf"');
        } catch (\Exception $e) {
            Log::error('Error processing Instagram URL: ' . $e->getMessage());
            return redirect('/')->with('error', 'Failed to generate PDF');
        }
    }
}