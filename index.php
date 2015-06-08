<?php

use Silex\Application;
use Silex\Provider;
use Symfony\Component\HttpFoundation\Request;

require_once 'vendor/autoload.php';

$app = new Application;
$app->register(new Provider\TwigServiceProvider, [
	'twig.path' => __DIR__
]);

$app['debug'] = true;

$app->get('/', function(Application $app) {
	return $app['twig']->render('index.html.twig');
});

$app->post('/', function(Application $app, Request $request) {
	$uploadedFile = $request->files->get('lock');
	if (empty($uploadedFile)) {
		return $app['twig']->render('index.html.twig', [
			'error' => 'Error handling uploaded file'
		]);
	}
	$data = json_decode(file_get_contents($uploadedFile->getPathname()), true);
	if(!is_array($data)) {
		return $app['twig']->render('index.html.twig', [
			'error' => 'Uploaded file doesn\'t containt JSON'
		]);
	}

	$match = false;
	$i = 0;
	foreach($data['packages'] as $package) {
		if(strpos($package['name'], 'zendframework/') === 0
			&& strpos($package['source']['url'], 'Component_') !== false
		) {
			$match = true;

			// convert "Component_ZendPackageName" to "zend-package-name"
			preg_match('/Component_[a-zA-Z0-9]+/', $package['source']['url'], $matches);
			if(empty($matches)) {
				return $app['twig']->render('index.html.twig', [
					'error' => sprintf('Could not detect old folder name of package "%s"', $package['name'])
				]);
			}

			// extract component name out of the package name
			$componentName = str_replace('zendframework/', '', $package['name']);

			// update source url and dist urls
			$data = igorw\assoc_in($data, ['packages', $i, 'source', 'url'],
				str_replace($matches[0], $componentName, $package['source']['url']));

			if(isset($package['dist']['url'])) {
				$data = igorw\assoc_in($data, ['packages', $i, 'dist', 'url'],
					str_replace($matches[0], $componentName, $package['dist']['url']));
			}
		}
		$i++;
	}

	if(!$match) {
		return $app['twig']->render('index.html.twig', [
			'error' => 'Update not required, no errors detected'
		]);
	}

	$response = $app->json($data);
	$response->setEncodingOptions(JSON_HEX_QUOT | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	$response->headers->set('Content-Disposition', 'attachment; filename="composer.lock";');
	return $response;
});

$app->run();