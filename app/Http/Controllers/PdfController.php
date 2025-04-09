<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use setasign\Fpdi\Fpdi;
use App\Services\CustomPdf;

class PdfController extends Controller
{
    public function generatePdf(Request $request)
    {
        // List of Instagram links
        $instagramLinks = [
            'https://www.instagram.com/dent_de_man/',
            'https://www.instagram.com/pontadope_oficial/',
            'https://www.instagram.com/parcpedagogiquesaintnectaire/',
            'https://www.instagram.com/lemontnimba/',
            'https://www.instagram.com/baiedessirenes/',
            'https://www.instagram.com/sofitelabidjan/',
            'https://www.instagram.com/stade_olympique_ado_ebimpe/',
            'https://www.instagram.com/hotelassiniebeach/',
            'https://www.instagram.com/bybloshotel.ci/',
            'https://www.instagram.com/allocodrome/',
            'https://www.instagram.com/grandmarcheqc/',
            'https://www.instagram.com/rueprincesse_official/',
            'https://www.instagram.com/petiteoccasion.reims/',
            'https://www.instagram.com/lacasableuecarquefou/',
            'https://www.instagram.com/ile_de_robinson/',
            'https://www.instagram.com/piscine_de_chamonix/',
        ];

        // Path to the local PDF template
        $pdfTemplatePath = public_path('files/Babipoly.pdf');

        // Check if the template exists
        if (!file_exists($pdfTemplatePath)) {
            return response()->json(['error' => 'PDF template not found.'], 404);
        }

        // Create a new CustomPdf instance
        $pdf = new CustomPdf();

        // Load all pages from the template PDF
        $pageCount = $pdf->setSourceFile($pdfTemplatePath); // Get the total number of pages

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo); // Import the current page
            $size = $pdf->getTemplateSize($templateId); // Get the size of the current page

            // Add a new page in landscape mode
            $pdf->AddPage('L', [$size['height'], $size['width']]); // Swap width and height for landscape mode

            // Use the template and scale it to fit the page
            $pdf->useTemplate($templateId);

            // Add Instagram photos and names only to the first page
            if ($pageNo === 1) {
                // Define custom coordinates for each image and name
                $coordinates = [
                    ['x' => 39, 'y' => 1],  // First image
                    ['x' => 85, 'y' => 1],  // Second image
                    ['x' => 108, 'y' => 1], // Third image
                    ['x' => 175, 'y' => 1], // Fourth image
                    ['x' => 10, 'y' => 60],  // Fifth image
                    ['x' => 60, 'y' => 60],  // Sixth image
                    ['x' => 110, 'y' => 60], // Seventh image
                    ['x' => 160, 'y' => 60], // Eighth image
                    // Add more coordinates as needed
                ];

                foreach ($instagramLinks as $index => $link) {
                    try {
                        // Fetch the HTML content of the Instagram profile page
                        $client = new \GuzzleHttp\Client();
                        $response = $client->get($link);

                        // Ensure the response status is 200 (OK)
                        if ($response->getStatusCode() === 200) {
                            $html = $response->getBody()->getContents(); // Correct way to get the response body as a string
                        } else {
                            throw new \Exception('Failed to fetch the page. Status code: ' . $response->getStatusCode());
                        }

                        // Use Symfony DomCrawler to parse the HTML
                        $crawler = new Crawler($html);

                        // Extract profile name
                        $profileName = $crawler->filter('meta[property="og:title"]')->attr('content');
                        $profileName = explode('(', $profileName)[0]; // Extract the name
                        $profileName = trim($profileName); // Remove any trailing spaces

                        // Extract profile image
                        $profileImage = $crawler->filter('meta[property="og:image"]')->attr('content');
                        $logoContent = file_get_contents($profileImage);

                        // Detect the MIME type of the image
                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $finfo->buffer($logoContent);

                        // Save the image temporarily
                        $tempImagePath = sys_get_temp_dir() . "/logo_$index";
                        file_put_contents($tempImagePath, $logoContent);

                        // Convert the image to PNG if necessary
                        $pngImagePath = sys_get_temp_dir() . "/logo_$index.png";
                        if ($mimeType !== 'image/png') {
                            $image = imagecreatefromstring($logoContent);
                            if ($image === false) {
                                throw new \Exception('Failed to create image from content.');
                            }
                            imagepng($image, $pngImagePath); // Convert to PNG
                            imagedestroy($image); // Free memory
                        } else {
                            // If already PNG, just copy the file
                            copy($tempImagePath, $pngImagePath);
                        }

                        // Get the custom coordinates for this image
                        $x = $coordinates[$index]['x'];
                        $y = $coordinates[$index]['y'];

                        // Rotate the first 4 images and names
                        if ($index < 4) {
                            $pdf->Rotate(180, $x + 10, $y + 10); // Rotate by 45 degrees around the center of the image
                        }

                        // Add the image to the PDF
                        $pdf->Image($pngImagePath, $x, $y, 20, 20); // Add the image (x, y, width, height)

                        // Add the profile name above the image
                        $pdf->SetFont('Arial', '', 6);
                        // Always place the name above the image
                        $nameY = $y - 10; // Fixed offset to place the name above the image
                        if ($nameY < 0) {
                            $nameY = 1; // Ensure the name stays within the page bounds
                        }
                        $pdf->SetXY($x, $nameY);
                        $pdf->Cell(20, 10, $profileName, 0, 0, 'C');

                        // Reset rotation after adding the image and name
                        if ($index < 4) {
                            $pdf->Rotate(0); // Reset rotation
                         }


                        // Clean up the temporary files
                        unlink($tempImagePath);
                        unlink($pngImagePath);
                    } catch (\Exception $e) {
                        Log::error('Error processing Instagram link: ' . $link . ' - ' . $e->getMessage());
                    }
                }
            }
        }

        // Output the modified PDF
        $pdfContent = $pdf->Output('S'); // 'S' returns the PDF as a string
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="modified.pdf"');
    }
}