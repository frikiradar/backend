<?php

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class srcDevDebugProjectContainerUrlGenerator extends Symfony\Component\Routing\Generator\UrlGenerator
{
    private static $declaredRoutes;
    private $defaultLocale;

    public function __construct(RequestContext $context, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->context = $context;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        if (null === self::$declaredRoutes) {
            self::$declaredRoutes = array(
        'board_list_all' => array(array('_format'), array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::getAllBoardAction'), array(), array(array('variable', '.', '[^/]++', '_format'), array('text', '/api/v1/board')), array(), array()),
        'board_list' => array(array('id', '_format'), array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::getBoardAction'), array(), array(array('variable', '.', '[^/]++', '_format'), array('variable', '/', '[^/\\.]++', 'id'), array('text', '/api/v1/board')), array(), array()),
        'board_add' => array(array('_format'), array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::addBoardAction'), array(), array(array('variable', '.', '[^/]++', '_format'), array('text', '/api/v1/board')), array(), array()),
        'board_edit' => array(array('id', '_format'), array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::editBoardAction'), array(), array(array('variable', '.', '[^/]++', '_format'), array('variable', '/', '[^/\\.]++', 'id'), array('text', '/api/v1/board')), array(), array()),
        'board_remove' => array(array('id', '_format'), array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::deleteBoardAction'), array(), array(array('variable', '.', '[^/]++', '_format'), array('variable', '/', '[^/\\.]++', 'id'), array('text', '/api/v1/board')), array(), array()),
        'task_add' => array(array('_format'), array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::addTaskAction'), array(), array(array('variable', '.', '[^/]++', '_format'), array('text', '/api/v1/task')), array(), array()),
        'task_edit' => array(array('id', '_format'), array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::editTaskAction'), array(), array(array('variable', '.', '[^/]++', '_format'), array('variable', '/', '[^/\\.]++', 'id'), array('text', '/api/v1/task')), array(), array()),
        'task_remove' => array(array('id', '_format'), array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::deleteTaskAction'), array(), array(array('variable', '.', '[^/]++', '_format'), array('variable', '/', '[^/\\.]++', 'id'), array('text', '/api/v1/task')), array(), array()),
        'api' => array(array(), array('_controller' => 'App\\Controller\\ApiController::api'), array(), array(array('text', '/api/v1/')), array(), array()),
        'user_login' => array(array(), array('_controller' => 'App\\Controller\\UsersController::getLoginAction'), array(), array(array('text', '/api/login')), array(), array()),
        'user_register' => array(array(), array('_controller' => 'App\\Controller\\UsersController::registerAction'), array(), array(array('text', '/api/register')), array(), array()),
        'app_users_get' => array(array(), array('_controller' => 'App\\Controller\\UsersController::getAction'), array(), array(array('text', '/api/v1/user')), array(), array()),
        'app_users_put' => array(array(), array('_controller' => 'App\\Controller\\UsersController::putAction'), array(), array(array('text', '/api/v1/user')), array(), array()),
        'coordinates' => array(array(), array('_controller' => 'App\\Controller\\UsersController::putCoordinatesAction'), array(), array(array('text', '/api/v1/coordinates')), array(), array()),
        'app.swagger' => array(array(), array('_controller' => 'nelmio_api_doc.controller.swagger'), array(), array(array('text', '/api/doc.json')), array(), array()),
        '_twig_error_test' => array(array('code', '_format'), array('_controller' => 'twig.controller.preview_error::previewErrorPageAction', '_format' => 'html'), array('code' => '\\d+'), array(array('variable', '.', '[^/]++', '_format'), array('variable', '/', '\\d+', 'code'), array('text', '/_error')), array(), array()),
        '_wdt' => array(array('token'), array('_controller' => 'web_profiler.controller.profiler::toolbarAction'), array(), array(array('variable', '/', '[^/]++', 'token'), array('text', '/_wdt')), array(), array()),
        '_profiler_home' => array(array(), array('_controller' => 'web_profiler.controller.profiler::homeAction'), array(), array(array('text', '/_profiler/')), array(), array()),
        '_profiler_search' => array(array(), array('_controller' => 'web_profiler.controller.profiler::searchAction'), array(), array(array('text', '/_profiler/search')), array(), array()),
        '_profiler_search_bar' => array(array(), array('_controller' => 'web_profiler.controller.profiler::searchBarAction'), array(), array(array('text', '/_profiler/search_bar')), array(), array()),
        '_profiler_phpinfo' => array(array(), array('_controller' => 'web_profiler.controller.profiler::phpinfoAction'), array(), array(array('text', '/_profiler/phpinfo')), array(), array()),
        '_profiler_search_results' => array(array('token'), array('_controller' => 'web_profiler.controller.profiler::searchResultsAction'), array(), array(array('text', '/search/results'), array('variable', '/', '[^/]++', 'token'), array('text', '/_profiler')), array(), array()),
        '_profiler_open_file' => array(array(), array('_controller' => 'web_profiler.controller.profiler::openAction'), array(), array(array('text', '/_profiler/open')), array(), array()),
        '_profiler' => array(array('token'), array('_controller' => 'web_profiler.controller.profiler::panelAction'), array(), array(array('variable', '/', '[^/]++', 'token'), array('text', '/_profiler')), array(), array()),
        '_profiler_router' => array(array('token'), array('_controller' => 'web_profiler.controller.router::panelAction'), array(), array(array('text', '/router'), array('variable', '/', '[^/]++', 'token'), array('text', '/_profiler')), array(), array()),
        '_profiler_exception' => array(array('token'), array('_controller' => 'web_profiler.controller.exception::showAction'), array(), array(array('text', '/exception'), array('variable', '/', '[^/]++', 'token'), array('text', '/_profiler')), array(), array()),
        '_profiler_exception_css' => array(array('token'), array('_controller' => 'web_profiler.controller.exception::cssAction'), array(), array(array('text', '/exception.css'), array('variable', '/', '[^/]++', 'token'), array('text', '/_profiler')), array(), array()),
        'app.swagger_ui' => array(array(), array('_controller' => 'nelmio_api_doc.controller.swagger_ui'), array(), array(array('text', '/api/doc')), array(), array()),
    );
        }
    }

    public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        $locale = $parameters['_locale']
            ?? $this->context->getParameter('_locale')
            ?: $this->defaultLocale;

        if (null !== $locale && (self::$declaredRoutes[$name.'.'.$locale][1]['_canonical_route'] ?? null) === $name && null !== $name) {
            unset($parameters['_locale']);
            $name .= '.'.$locale;
        } elseif (!isset(self::$declaredRoutes[$name])) {
            throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $name));
        }

        list($variables, $defaults, $requirements, $tokens, $hostTokens, $requiredSchemes) = self::$declaredRoutes[$name];

        return $this->doGenerate($variables, $defaults, $requirements, $tokens, $parameters, $name, $referenceType, $hostTokens, $requiredSchemes);
    }
}
