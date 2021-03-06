<?php
namespace AzuraCast\Middleware\Module;

use AzuraCast\Radio\Backend\BackendAbstract;
use Entity;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Module middleware for the file management pages.
 */
class StationFiles
{
    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $next): Response
    {
        /** @var Entity\Station $station */
        $station = $request->getAttribute('station');

        /** @var BackendAbstract $backend */
        $backend = $request->getAttribute('station_backend');

        if (!$backend->supportsMedia()) {
            throw new \App\Exception(__('This feature is not currently supported on this station.'));
        }

        $base_dir = $station->getRadioMediaDir();

        $file = $request->getParam('file', '');
        $file_path = realpath($base_dir . '/' . $file);

        if ($file_path === false) {
            return $response->withStatus(404)
                ->withJson(['error' => ['code' => 404, 'msg' => 'File or Directory Not Found']]);
        }

        // Sanity check that the final file path is still within the base directory
        if (substr($file_path, 0, strlen($base_dir)) !== $base_dir) {
            return $response->withStatus(403)
                ->withJson(['error' => ['code' => 403, 'msg' => 'Forbidden']]);
        }

        $request = $request->withAttribute('file', $file)
            ->withAttribute('file_path', $file_path)
            ->withAttribute('base_dir', $base_dir);

        return $next($request, $response);
    }


}