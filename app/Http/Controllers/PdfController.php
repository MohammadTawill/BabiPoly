<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use setasign\Fpdi\Fpdi;

class PdfController extends Controller
{
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
            $pdfTemplatePath = public_path('files/Babipoly x Byblos Hotel.pdf');

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
                         // Add two profile images on page 1
                         $pdf->Image($logoPath, 200, 50, 100, 100); //(x, y, width, height)
                    } elseif ($pageNo === 3) {
                        // Add two profile images on page 3
                        $pdf->Image($logoPath, 40, 25, 70, 70); //(x, y, width, height)
                    } elseif ($pageNo === 4) {
                        // Add two profile images on page 4
                        $pdf->Image($logoPath, 119, 162, 8, 8); // First image (x, y, width, height)
                        $pdf->Image($logoPath, 217, 114, 35, 32); // Second image (x, y, width, height)
                    } elseif ($pageNo === 5) {
                        // Add three profile images on page 5
                        $pdf->Image($logoPath, 238, 28, 20, 20); // First image
                        $pdf->Image($logoPath, 200, 126, 13, 13); // Second image
                        $pdf->Image($logoPath, 280, 126, 13, 13); // Third image
                    } else {
                        // Add one profile image on page 8
                        $pdf->Image($logoPath, 175, 80, 25, 25); // Adjust position (x, y) and size (width, height)
                    }

                    // Clean up the temporary file
                    unlink($logoPath);
                }

                // Add the profile name only to the fourth page
                if ($pageNo === 4) {
                    $pdf->SetFont('Arial', '', 4); // Use Arial with a larger font size
                    $pdf->SetXY(117.3, 155.5); // Adjust position (x, y)
                    $profileName = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $profileName); // Convert the profile name to ISO-8859-1 encoding
                    $pdf->Write(10, $profileName); // Write the profile name

                    // Add the profile name to the second image
                    $pdf->SetFont('Arial', '', 15); // Use Arial with a larger font size
                    $pdf->SetXY(216, 105.5); // Adjust position (x, y)
                    $profileName = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $profileName); // Convert the profile name to ISO-8859-1 encoding
                    $pdf->Write(10, $profileName); // Write the profile name
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