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
        $username = "bybloshotel.ci";
        $instagramUrl = "https://www.instagram.com/{$username}/";

        // Fetch the HTML content of the Instagram profile page
        $client = new \GuzzleHttp\Client();
        $response = $client->get($instagramUrl);
        $html = (string) $response->getBody();

        // Use Symfony DomCrawler to parse the HTML
        $crawler = new Crawler($html);

        try {
            // Extract profile name
            $profileName = $crawler->filter('meta[property="og:title"]')->attr('content');
            $profileName = explode('Abidjan', $profileName)[0]; // Split at "Abidjan" and take the first part
            $profileName = trim($profileName); // Remove any trailing spaces

            // Extract profile image from Instagram
            $profileImage = $crawler->filter('meta[property="og:image"]')->attr('content');
            $logoUrl = $profileImage; // Use the Instagram profile image as the logo
        } catch (\Exception $e) {
            Log::error('Unable to fetch profile data from HTML: ' . $e->getMessage());
            return response()->json(['error' => 'Unable to fetch profile data'], 400);
        }

        // Path to the local PDF template
        $pdfTemplatePath = public_path('files/Babipoly x Byblos Hotel.pdf');

        // Create a new FPDI instance
        $pdf = new Fpdi();

        // Load all pages from the template PDF
        $pageCount = $pdf->setSourceFile($pdfTemplatePath); // Get the total number of pages

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo); // Import the current page
            $size = $pdf->getTemplateSize($templateId); // Get the size of the current page

            // Add a new page in landscape mode
            $pdf->AddPage('L', [$size['height'], $size['width']]); // Swap width and height for landscape mode

            // Get the dimensions of the output page
            $pageWidth = $pdf->GetPageWidth();
            $pageHeight = $pdf->GetPageHeight();

            // Calculate scaling to fit the imported page into the output page
            $scaleWidth = $pageWidth / $size['width'];
            $scaleHeight = $pageHeight / $size['height'];
            $scale = min($scaleWidth, $scaleHeight); // Use the smaller scale to fit the page

            // Center the imported page on the output page
            $x = ($pageWidth - $size['width'] * $scale) / 2;
            $y = ($pageHeight - $size['height'] * $scale) / 2;

            // Use the template and scale it to fit the page
            $pdf->useTemplate($templateId, $x, $y, $size['width'] * $scale, $size['height'] * $scale);

            // Add the logo and profile name only to the fourth page
            if ($pageNo === 4) {
                // Fetch the logo content from the URL
                $logoContent = file_get_contents($logoUrl);

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
                $pdf->Image($logoPath, 119, 162, 8, 8); // Adjust position (x, y) and size (width, height)

                // Clean up the temporary file
                unlink($logoPath);

                // Add the profile name
                $pdf->SetFont('Arial', '', 5); // Use Arial with a larger font size
                $pdf->SetXY(116.6, 155.5); // Adjust position (x, y)
                $profileName = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $profileName); // Convert the profile name to ISO-8859-1 encoding
                $pdf->Write(10, $profileName); // Write the profile name
            }
        }

        // Output the modified PDF
        $pdfContent = $pdf->Output('S'); // 'S' returns the PDF as a string
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="modified.pdf"');
    }
}