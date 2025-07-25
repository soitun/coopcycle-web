<?php

namespace AppBundle\Controller;

use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Spreadsheet\ProductSpreadsheetParser;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class SpreadsheetController extends AbstractController
{
    #[Route(path: '/spreadsheets/examples/coopcycle-products-example.csv', name: 'spreadsheet_example_products')]
    public function productsExampleCsvAction(ProductSpreadsheetParser $parser, SerializerInterface $serializer)
    {
        $csv = $serializer->serialize($parser->getExampleData(), 'csv');

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'coopcycle-products-example.csv'
        ));

        return $response;
    }

    #[Route(path: '/spreadsheets/examples/coopcycle-tasks-example.csv', name: 'spreadsheet_example_tasks')]
    public function tasksExampleCsvAction(TaskSpreadsheetParser $parser, SerializerInterface $serializer)
    {
        $csv = $serializer->serialize($parser->getExampleData(), 'csv');

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'coopcycle-tasks-example.csv'
        ));

        return $response;
    }

    #[Route(path: '/spreadsheets/examples/coopcycle-deliveries-example.csv', name: 'spreadsheet_example_deliveries')]
    public function deliveriesExampleCsvAction(DeliverySpreadsheetParser $parser, SerializerInterface $serializer)
    {
        $csv = $serializer->serialize($parser->getExampleData(), 'csv');

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'coopcycle-deliveries-example.csv'
        ));

        return $response;
    }
}
