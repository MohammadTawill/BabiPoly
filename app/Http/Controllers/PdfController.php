<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use Symfony\Component\DomCrawler\Crawler;

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
            $profileName = preg_split('/\s*\(/', $profileName)[0]; // Split at '(' and take the first part


            // Extract profile image
            $profileImage = $crawler->filter('meta[property="og:image"]')->attr('content');
        } catch (\Exception $e) {
            Log::error('Unable to fetch profile data from HTML: ' . $e->getMessage());
            return response()->json(['error' => 'Unable to fetch profile data'], 400);
        }

        // Generate PDF
        $mpdf = new Mpdf();
        $mpdf->WriteHTML("<h1>{$profileName}</h1>");
        $mpdf->WriteHTML("<img src='{$profileImage}' width='150' />");
        $pdfOutput = $mpdf->Output('', 'S');

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename=\"instagram-profile.pdf\"');
    }
}