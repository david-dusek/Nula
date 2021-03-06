<?php

namespace Nula\I18n;

use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Finder\Iterator\FilenameFilterIterator;
use Symfony\Component\Finder\SplFileInfo;

class LocaleManager {

  const LOCALE_KEY = 'lang';
  const DEFAULT_LOCALE = 'cs_CZ';
  const LOCALE_FILES_BASE_DIR = '../app/lang';
  const LOCALE_FILES_FORMAT = 'php';

  /**
   * @var \Slim\Router
   */
  private $router;

  /**
   * @var \Nula\View\Factory
   */
  private $viewFactory;

  /**
   * LocaleManager constructor.
   * @param \Slim\Router $router
   */
  public function __construct(\Slim\Router $router, \Nula\View\Factory $viewFactory) {
    $this->router = $router;
    $this->viewFactory = $viewFactory;
  }

  /**
   * @param \Slim\Http\Request $request
   * @param array $routeArguments
   * @return \Slim\Views\Twig
   * @throws \Exception
   */
  public function createLocalizedTwigView(\Slim\Http\Request $request, array $routeArguments): \Slim\Views\Twig {
    $locale = $this->getLocaleCodeFromRouteArguments($routeArguments);
    $translationExtension = $this->createTranslatorExstension($locale);
    $view = $this->viewFactory->createTwigView($request, $this->router);
    $view->addExtension($translationExtension);

    return $view;
  }

  /**
   * @param \Slim\Http\Request $request
   * @param array $routeArguments
   * @return array
   */
  public function getLocalizedTwigViewTemplateParameters(\Slim\Http\Request $request, array $routeArguments, Response $response): array {
    return [
      'lang' => $this->localeToRouteArgumentsFormat($this->getLocaleCodeFromRouteArguments($routeArguments)),
      'locales' => $response->isOk() ? $this->getLocales($request, $routeArguments) : [],
    ];
  }

  /**
   * @param \Slim\Http\Request $request
   * @param array $routeArguments
   * @return array
   */
  private function getLocales(\Slim\Http\Request $request, array $routeArguments) {
    $route = $request->getAttribute('route');
    if (is_null($route)) {
      return [];
    }
    /* @var $route \Slim\Route */
    $routeName = $route->getName() ?? 'homepage';
    $currentLocale = $this->getLocaleCodeFromRouteArguments($routeArguments);

    $locales = [];
    foreach ($this->getSupportedLocales() as $locale) {
      $abbreviation = $this->extractLangCodeFromLocale($locale);
      $routeArguments[self::LOCALE_KEY] = $this->localeToRouteArgumentsFormat($locale);
      $localeUrl = $this->router->pathFor($routeName, $routeArguments);
      $isActive = $currentLocale === $locale;
      $locales[$abbreviation] = new Locale($abbreviation, $localeUrl, $isActive);
    }
    ksort($locales);

    return $locales;
  }

  /**
   * @param array $routeArguments
   * @return string
   */
  public function getLocaleCodeFromRouteArguments(array $routeArguments): string {
    if (isset($routeArguments[self::LOCALE_KEY])) {
      $localeCode = $this->localeToFilenameFormat($routeArguments[self::LOCALE_KEY]);
      $this->checkLocaleSupported($localeCode);
    } else {
      $localeCode = self::DEFAULT_LOCALE;
    }

    return $localeCode;
  }

  /**
   * @param Request $request
   * @return string
   */
  public function getLocaleCodeFromPath(Request $request): string {
    $matches = [];
    if (preg_match('%^/([a-z]{2}-[A-Z]{2})/?.*%', $request->getUri()->getPath(), $matches)) {
      $localeFromPath = $this->localeToFilenameFormat($matches[1]);

      if ($this->isLocaleSupported($localeFromPath)) {
        $locale = $localeFromPath;
      } else {
        $locale = self::DEFAULT_LOCALE;
      }
    } else {
      $locale = self::DEFAULT_LOCALE;
    }

    return $locale;
  }

  /**
   * @param string $locale
   * @return \Symfony\Bridge\Twig\Extension\TranslationExtension
   * @throws \Exception
   */
  private function createTranslatorExstension(string $locale): \Symfony\Bridge\Twig\Extension\TranslationExtension {
    $this->checkLocaleSupported($locale);
    $translator = new \Symfony\Component\Translation\Translator($locale,
      new \Symfony\Component\Translation\MessageSelector());
    $translator->setFallbackLocales([self::DEFAULT_LOCALE]);
    $translator->addLoader(self::LOCALE_FILES_FORMAT, new \Symfony\Component\Translation\Loader\PhpFileLoader());
    $translator->addResource(self::LOCALE_FILES_FORMAT, $this->getFileByLocaleCode($locale), $locale);

    return new \Symfony\Bridge\Twig\Extension\TranslationExtension($translator);
  }

  /**
   * @param string $localeCode
   * @return string
   * @throws \Exception
   */
  private function getFileByLocaleCode(string $localeCode): string {
    $filePath = self::LOCALE_FILES_BASE_DIR . '/' . $localeCode . '.' . self::LOCALE_FILES_FORMAT;
    if (is_file($filePath) && is_readable($filePath)) {
      return $filePath;
    }

    throw new \Exception("Locale file $filePath not exists or is not readable.");
  }

  /**
   * @param string $locale
   */
  private function checkLocaleSupported(string $locale) {
    if (!$this->isLocaleSupported($locale)) {
      throw new \InvalidArgumentException("Locale '$locale' is not supported yet.");
    }
  }

  /**
   * @param string $locale
   * @return bool
   */
  private function isLocaleSupported(string $locale) {
    $supportedLocales = $this->getSupportedLocales();
    return array_key_exists($locale, $supportedLocales);
  }

  /**
   * @return string[]
   */
  private function getSupportedLocales(): array {
    $languagesDirectoryIterator = new \DirectoryIterator(self::LOCALE_FILES_BASE_DIR);
    $languagesFilesIterator = new FilenameFilterIterator($languagesDirectoryIterator, ['/[a-z]{2}_[A-Z]{2}\.php/'], []);

    $supportedLocales = [];
    foreach ($languagesFilesIterator as $item) {
      /* @var $item SplFileInfo */
      $localeCode = $item->getBasename('.' . $item->getExtension());
      $supportedLocales[$localeCode] = $localeCode;
    }

    return $supportedLocales;
  }

  /**
   * @param $locale
   * @return string
   */
  public function extractLangCodeFromLocale($locale): string {
    return substr($locale, 0, strpos($locale, '_'));
  }

  /**
   * @param string $locale
   * @return string
   */
  public function localeToRouteArgumentsFormat(string $locale): string {
    return str_replace('_', '-', $locale);
  }

  /**
   * @param string $locale
   * @return string
   */
  private function localeToFilenameFormat(string $locale): string {
    return str_replace('-', '_', $locale);
  }

}