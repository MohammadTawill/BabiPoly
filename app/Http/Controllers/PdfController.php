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
           // $profileName = preg_split('/\s*\(/', $profileName)[0]; // Split at '(' and take the first part

            // Extract the part of the name before the word "Abidjan" ()
            $profileName = explode('Abidjan', $profileName)[0]; // Split at "Abidjan" and take the first part
            $profileName = trim($profileName); // Remove any trailing spaces



            // Extract profile image from instagram or website logo 
           // $profileImage = $crawler->filter('meta[property="og:image"]')->attr('content'); instagram image
           $logoUrl = 'https://bybloshotelci.com/storage/2024/02/cropped-logo-255x247.png'; // website Logo
        } catch (\Exception $e) {
            Log::error('Unable to fetch profile data from HTML: ' . $e->getMessage());
            return response()->json(['error' => 'Unable to fetch profile data'], 400);
        }

        // Generate PDF
        $mpdf = new Mpdf();
        $mpdf->WriteHTML("<h1>{$profileName}</h1>");
        $mpdf->WriteHTML("<img src='{$logoUrl}' width='150' />"); // instagram image or logo from website
        $pdfOutput = $mpdf->Output('', 'S');

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename=\"instagram-profile.pdf\"');
    }
}