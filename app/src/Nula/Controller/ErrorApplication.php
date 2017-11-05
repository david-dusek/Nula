<?php

namespace Nula\Controller;


use Slim\Http\Request;
use Slim\Http\Response;

class ErrorApplication extends Base {

  public function __invoke(Request $request, Response $response, \Exception $exception) {
    $routeArguments = [];
    $routeArguments[$this->localeManager::LOCALE_KEY] = $this->localeManager->getLocaleCodeFromPath($request);

    return $this->createTwigLocalizedResponse($request, $response->withStatus(503), $routeArguments, 'error/503.twig');
  }

}