<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class srcDevDebugProjectContainerUrlMatcher extends Symfony\Bundle\FrameworkBundle\Routing\RedirectableUrlMatcher
{
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($pathinfo)
    {
        $allow = $allowSchemes = array();
        if ($ret = $this->doMatch($pathinfo, $allow, $allowSchemes)) {
            return $ret;
        }
        if ($allow) {
            throw new MethodNotAllowedException(array_keys($allow));
        }
        if (!in_array($this->context->getMethod(), array('HEAD', 'GET'), true)) {
            // no-op
        } elseif ($allowSchemes) {
            redirect_scheme:
            $scheme = $this->context->getScheme();
            $this->context->setScheme(key($allowSchemes));
            try {
                if ($ret = $this->doMatch($pathinfo)) {
                    return $this->redirect($pathinfo, $ret['_route'], $this->context->getScheme()) + $ret;
                }
            } finally {
                $this->context->setScheme($scheme);
            }
        } elseif ('/' !== $trimmedPathinfo = rtrim($pathinfo, '/') ?: '/') {
            $pathinfo = $trimmedPathinfo === $pathinfo ? $pathinfo.'/' : $trimmedPathinfo;
            if ($ret = $this->doMatch($pathinfo, $allow, $allowSchemes)) {
                return $this->redirect($pathinfo, $ret['_route']) + $ret;
            }
            if ($allowSchemes) {
                goto redirect_scheme;
            }
        }

        throw new ResourceNotFoundException();
    }

    private function doMatch(string $pathinfo, array &$allow = array(), array &$allowSchemes = array()): array
    {
        $allow = $allowSchemes = array();
        $pathinfo = rawurldecode($pathinfo) ?: '/';
        $trimmedPathinfo = rtrim($pathinfo, '/') ?: '/';
        $context = $this->context;
        $requestMethod = $canonicalMethod = $context->getMethod();

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        switch ($trimmedPathinfo) {
            case '/api/v1/user':
                // app_users_get
                if ('/' !== $pathinfo && $trimmedPathinfo !== $pathinfo) {
                    if ('GET' === $canonicalMethod) {
                        return $allow = $allowSchemes = array();
                    }
                    goto not_app_users_get;
                }

                $ret = array('_route' => 'app_users_get', '_controller' => 'App\\Controller\\UsersController::getAction');
                if (!isset(($a = array('GET' => 0))[$canonicalMethod])) {
                    $allow += $a;
                    goto not_app_users_get;
                }

                return $ret;
                not_app_users_get:
                // app_users_put
                if ('/' !== $pathinfo && $trimmedPathinfo !== $pathinfo) {
                    goto not_app_users_put;
                }

                $ret = array('_route' => 'app_users_put', '_controller' => 'App\\Controller\\UsersController::putAction');
                if (!isset(($a = array('PUT' => 0))[$requestMethod])) {
                    $allow += $a;
                    goto not_app_users_put;
                }

                return $ret;
                not_app_users_put:
                break;
            default:
                $routes = array(
                    '/api/v1' => array(array('_route' => 'api', '_controller' => 'App\\Controller\\ApiController::api'), null, null, null, true),
                    '/api/login' => array(array('_route' => 'user_login', '_controller' => 'App\\Controller\\UsersController::getLoginAction'), null, array('POST' => 0), null, false),
                    '/api/register' => array(array('_route' => 'user_register', '_controller' => 'App\\Controller\\UsersController::registerAction'), null, array('POST' => 0), null, false),
                    '/api/v1/coordinates' => array(array('_route' => 'coordinates', '_controller' => 'App\\Controller\\UsersController::putCoordinatesAction'), null, array('PUT' => 0), null, false),
                    '/api/doc.json' => array(array('_route' => 'app.swagger', '_controller' => 'nelmio_api_doc.controller.swagger'), null, array('GET' => 0), null, false),
                    '/_profiler' => array(array('_route' => '_profiler_home', '_controller' => 'web_profiler.controller.profiler::homeAction'), null, null, null, true),
                    '/_profiler/search' => array(array('_route' => '_profiler_search', '_controller' => 'web_profiler.controller.profiler::searchAction'), null, null, null, false),
                    '/_profiler/search_bar' => array(array('_route' => '_profiler_search_bar', '_controller' => 'web_profiler.controller.profiler::searchBarAction'), null, null, null, false),
                    '/_profiler/phpinfo' => array(array('_route' => '_profiler_phpinfo', '_controller' => 'web_profiler.controller.profiler::phpinfoAction'), null, null, null, false),
                    '/_profiler/open' => array(array('_route' => '_profiler_open_file', '_controller' => 'web_profiler.controller.profiler::openAction'), null, null, null, false),
                    '/api/doc' => array(array('_route' => 'app.swagger_ui', '_controller' => 'nelmio_api_doc.controller.swagger_ui'), null, array('GET' => 0), null, false),
                );

                if (!isset($routes[$trimmedPathinfo])) {
                    break;
                }
                list($ret, $requiredHost, $requiredMethods, $requiredSchemes, $hasTrailingSlash) = $routes[$trimmedPathinfo];
                if ('/' !== $pathinfo && $hasTrailingSlash === ($trimmedPathinfo === $pathinfo)) {
                    if ('GET' === $canonicalMethod && (!$requiredMethods || isset($requiredMethods['GET']))) {
                        return $allow = $allowSchemes = array();
                    }
                    break;
                }

                $hasRequiredScheme = !$requiredSchemes || isset($requiredSchemes[$context->getScheme()]);
                if ($requiredMethods && !isset($requiredMethods[$canonicalMethod]) && !isset($requiredMethods[$requestMethod])) {
                    if ($hasRequiredScheme) {
                        $allow += $requiredMethods;
                    }
                    break;
                }
                if (!$hasRequiredScheme) {
                    $allowSchemes += $requiredSchemes;
                    break;
                }

                return $ret;
        }

        $matchedPathinfo = $pathinfo;
        $regexList = array(
            0 => '{^(?'
                    .'|/api/v1/(?'
                        .'|board(?'
                            .'|(?:\\.([^/]++))?(*:41)'
                            .'|/([^/\\.]++)(?:\\.([^/]++))?(*:74)'
                            .'|(?:\\.([^/]++))?(*:96)'
                            .'|/([^/\\.]++)(?:\\.([^/]++))?(?'
                                .'|(*:132)'
                            .')'
                        .')'
                        .'|task(?'
                            .'|(?:\\.([^/]++))?(*:164)'
                            .'|/([^/\\.]++)(?:\\.([^/]++))?(?'
                                .'|(*:201)'
                            .')'
                        .')'
                    .')'
                    .'|/_(?'
                        .'|error/(\\d+)(?:\\.([^/]++))?(*:243)'
                        .'|wdt/([^/]++)(*:263)'
                        .'|profiler/([^/]++)(?'
                            .'|/(?'
                                .'|search/results(*:309)'
                                .'|router(*:323)'
                                .'|exception(?'
                                    .'|(*:343)'
                                    .'|\\.css(*:356)'
                                .')'
                            .')'
                            .'|(*:366)'
                        .')'
                    .')'
                .')/?$}sD',
        );

        foreach ($regexList as $offset => $regex) {
            while (preg_match($regex, $matchedPathinfo, $matches)) {
                switch ($m = (int) $matches['MARK']) {
                    case 132:
                        // board_edit
                        if ($trimmedPathinfo === $pathinfo) {
                            // no-op
                        } elseif (preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        } elseif ('/' !== $pathinfo) {
                            goto not_board_edit;
                        }

                        $matches = array('id' => $matches[1] ?? null, '_format' => $matches[2] ?? null);

                        $ret = $this->mergeDefaults(array('_route' => 'board_edit') + $matches, array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::editBoardAction'));
                        if (!isset(($a = array('PUT' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_board_edit;
                        }

                        return $ret;
                        not_board_edit:

                        // board_remove
                        if ($trimmedPathinfo === $pathinfo) {
                            // no-op
                        } elseif (preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        } elseif ('/' !== $pathinfo) {
                            goto not_board_remove;
                        }

                        $ret = $this->mergeDefaults(array('_route' => 'board_remove') + $matches, array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::deleteBoardAction'));
                        if (!isset(($a = array('DELETE' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_board_remove;
                        }

                        return $ret;
                        not_board_remove:

                        break;
                    case 201:
                        // task_edit
                        if ($trimmedPathinfo === $pathinfo) {
                            // no-op
                        } elseif (preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        } elseif ('/' !== $pathinfo) {
                            goto not_task_edit;
                        }

                        $matches = array('id' => $matches[1] ?? null, '_format' => $matches[2] ?? null);

                        $ret = $this->mergeDefaults(array('_route' => 'task_edit') + $matches, array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::editTaskAction'));
                        if (!isset(($a = array('PUT' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_task_edit;
                        }

                        return $ret;
                        not_task_edit:

                        // task_remove
                        if ($trimmedPathinfo === $pathinfo) {
                            // no-op
                        } elseif (preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        } elseif ('/' !== $pathinfo) {
                            goto not_task_remove;
                        }

                        $ret = $this->mergeDefaults(array('_route' => 'task_remove') + $matches, array('_format' => 'json', '_controller' => 'App\\Controller\\ApiController::deleteTaskAction'));
                        if (!isset(($a = array('DELETE' => 0))[$requestMethod])) {
                            $allow += $a;
                            goto not_task_remove;
                        }

                        return $ret;
                        not_task_remove:

                        break;
                    default:
                        $routes = array(
                            41 => array(array('_route' => 'board_list_all', '_format' => 'json', '_controller' => 'App\\Controller\\ApiController::getAllBoardAction'), array('_format'), array('GET' => 0), null, false, true),
                            74 => array(array('_route' => 'board_list', '_format' => 'json', '_controller' => 'App\\Controller\\ApiController::getBoardAction'), array('id', '_format'), array('GET' => 0), null, false, true),
                            96 => array(array('_route' => 'board_add', '_format' => 'json', '_controller' => 'App\\Controller\\ApiController::addBoardAction'), array('_format'), array('POST' => 0), null, false, true),
                            164 => array(array('_route' => 'task_add', '_format' => 'json', '_controller' => 'App\\Controller\\ApiController::addTaskAction'), array('_format'), array('POST' => 0), null, false, true),
                            243 => array(array('_route' => '_twig_error_test', '_controller' => 'twig.controller.preview_error::previewErrorPageAction', '_format' => 'html'), array('code', '_format'), null, null, false, true),
                            263 => array(array('_route' => '_wdt', '_controller' => 'web_profiler.controller.profiler::toolbarAction'), array('token'), null, null, false, true),
                            309 => array(array('_route' => '_profiler_search_results', '_controller' => 'web_profiler.controller.profiler::searchResultsAction'), array('token'), null, null, false, false),
                            323 => array(array('_route' => '_profiler_router', '_controller' => 'web_profiler.controller.router::panelAction'), array('token'), null, null, false, false),
                            343 => array(array('_route' => '_profiler_exception', '_controller' => 'web_profiler.controller.exception::showAction'), array('token'), null, null, false, false),
                            356 => array(array('_route' => '_profiler_exception_css', '_controller' => 'web_profiler.controller.exception::cssAction'), array('token'), null, null, false, false),
                            366 => array(array('_route' => '_profiler', '_controller' => 'web_profiler.controller.profiler::panelAction'), array('token'), null, null, false, true),
                        );

                        list($ret, $vars, $requiredMethods, $requiredSchemes, $hasTrailingSlash, $hasTrailingVar) = $routes[$m];

                        if ($trimmedPathinfo === $pathinfo || !$hasTrailingVar) {
                            // no-op
                        } elseif (preg_match($regex, rtrim($matchedPathinfo, '/') ?: '/', $n) && $m === (int) $n['MARK']) {
                            $matches = $n;
                        } else {
                            $hasTrailingSlash = true;
                        }
                        if ('/' !== $pathinfo && $hasTrailingSlash === ($trimmedPathinfo === $pathinfo)) {
                            if ('GET' === $canonicalMethod && (!$requiredMethods || isset($requiredMethods['GET']))) {
                                return $allow = $allowSchemes = array();
                            }
                            if ($trimmedPathinfo === $pathinfo || !$hasTrailingVar) {
                                break;
                            }
                        }

                        foreach ($vars as $i => $v) {
                            if (isset($matches[1 + $i])) {
                                $ret[$v] = $matches[1 + $i];
                            }
                        }

                        $hasRequiredScheme = !$requiredSchemes || isset($requiredSchemes[$context->getScheme()]);
                        if ($requiredMethods && !isset($requiredMethods[$canonicalMethod]) && !isset($requiredMethods[$requestMethod])) {
                            if ($hasRequiredScheme) {
                                $allow += $requiredMethods;
                            }
                            break;
                        }
                        if (!$hasRequiredScheme) {
                            $allowSchemes += $requiredSchemes;
                            break;
                        }

                        return $ret;
                }

                if (366 === $m) {
                    break;
                }
                $regex = substr_replace($regex, 'F', $m - $offset, 1 + strlen($m));
                $offset += strlen($m);
            }
        }
        if ('/' === $pathinfo && !$allow && !$allowSchemes) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        return array();
    }
}
