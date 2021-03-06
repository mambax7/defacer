<?php
/**
 * Defacer
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       XOOPS Project (https://xoops.org)
 * @license         http://www.fsf.org/copyleft/gpl.html GNU public license
 * @package         Defacer
 * @since           2.4.0
 * @author          trabis <lusopoemas@gmail.com>
 */

use XoopsModules\Defacer\{
    Helper
};
/** @var Admin $adminObject */
/** @var Helper $helper */

/**
 * Profile core preloads
 *
 * @copyright       XOOPS Project (https://xoops.org)
 * @license         http://www.fsf.org/copyleft/gpl.html GNU public license
 * @author          trabis <lusopoemas@gmail.com>
 */
class DefacerCorePreload extends \XoopsPreloadItem
{
    // to add PSR-4 autoloader

    /**
     * @param $args
     */
    public static function eventCoreIncludeCommonEnd($args)
    {
        require_once __DIR__ . '/autoloader.php';
    }

    /**
     * @param $args
     */
    public static function eventCoreHeaderStart($args)
    {
        if (is_file($filename = XOOPS_ROOT_PATH . '/modules/defacer/include/beforeheader.php')) {
            require_once $filename;
        }
    }

    /**
     * @param $args
     */
    public static function eventCoreFooterStart($args)
    {
        if (is_file($filename = XOOPS_ROOT_PATH . '/modules/defacer/include/beforefooter.php')) {
            require_once $filename;
        }
    }

    /**
     * @param $args
     */
    public static function eventCoreHeaderAddmeta($args)
    {
        if (self::isRedirectActive()) {
            if (!empty($_SESSION['redirect_message'])) {
                global $xoTheme;
                $xoTheme->addScript('browse.php?Frameworks/jquery/jquery.js');
                $xoTheme->addScript('browse.php?Frameworks/jquery/plugins/jquery.jgrowl.js');
                $xoTheme->addStylesheet('modules/defacer/assets/js/jquery.jgrowl.css', ['media' => 'screen']);

                $xoTheme->addScript(
                    '',
                    null,
                    '
                    (function($){
                        $(document).ready(function(){
                            $.jGrowl("' . $_SESSION['redirect_message'] . '", {position:"center"});
                        });
                    })(jQuery);
                '
                );
                //{ life:5000 , position:\'bottom-left\', speed:\'slow\' }
                unset($_SESSION['redirect_message']);
            }
        }
    }

    /**
     * @param $args
     */
    public static function eventSystemClassGuiHeader($args)
    {
        self:: eventCoreHeaderAddmeta($args);
    }

    /**
     * @param $args
     */
    public function eventCoreIncludeFunctionsRedirectheader($args)
    {
        if (self::isRedirectActive() && !headers_sent()) {
            global $xoopsConfig;
            if (!empty($_SERVER['REQUEST_URI']) && false !== mb_strpos($_SERVER['REQUEST_URI'], 'user.php?op=logout')) {
                unset($_SESSION['redirect_message']);

                return;
            }
            [$url, $time, $message, $addredirect, $allowExternalLink] = $args;

            if (preg_match('/[\\0-\\31]|about:|script:/i', $url)) {
                if (!preg_match('/^\b(java)?script:([\s]*)history\.go\(-[0-9]*\)([\s]*[;]*[\s]*)$/si', $url)) {
                    $url = XOOPS_URL;
                }
            }

            if (!$allowExternalLink && $pos = mb_strpos($url, '://')) {
                $xoopsLocation = mb_substr(XOOPS_URL, mb_strpos(XOOPS_URL, '://') + 3);
                if (strcasecmp(mb_substr($url, $pos + 3, mb_strlen($xoopsLocation)), $xoopsLocation)) {
                    $url = XOOPS_URL;
                }
            }

            if (!empty($_SERVER['REQUEST_URI']) && $addredirect && false !== mb_strpos($url, 'user.php')) {
                if (false === mb_strpos($url, '?')) {
                    $url .= '?xoops_redirect=' . urlencode($_SERVER['REQUEST_URI']);
                } else {
                    $url .= '&amp;xoops_redirect=' . urlencode($_SERVER['REQUEST_URI']);
                }
            }

            if (defined('SID') && SID
                && (!isset($_COOKIE[session_name()])
                    || ($xoopsConfig['use_mysession']
                        && '' != $xoopsConfig['session_name']
                        && !isset($_COOKIE[$xoopsConfig['session_name']])))) {
                if (false === mb_strpos($url, '?')) {
                    $url .= '?' . SID;
                } else {
                    $url .= '&amp;' . SID;
                }
            }

            $url                          = preg_replace('/&amp;/i', '&', htmlspecialchars($url, ENT_QUOTES));
            $message                      = '' != trim($message) ? $message : _TAKINGBACK;
            $_SESSION['redirect_message'] = $message;
            header('Location: ' . $url);
            exit();
        }
    }

    /**
     * @param $args
     */
    public static function eventCoreClassTheme_blocksRetrieveBlocks($args)
    {
        //$args[2] = [];
        $class     = &$args[0];
        $template  = &$args[1];
        $block_arr = &$args[2];

        foreach ($block_arr as $key => $xobject) {
            if (0 !== mb_strpos($xobject->getVar('title'), '_')) {
                continue;
            }

            $block = [
                'id'      => $xobject->getVar('bid'),
                'module'  => $xobject->getVar('dirname'),
                'title'   => ltrim($xobject->getVar('title'), '_'),
                'weight'  => $xobject->getVar('weight'),
                'lastmod' => $xobject->getVar('last_modified'),
            ];

            $bcachetime = (int)$xobject->getVar('bcachetime');
            if (empty($bcachetime)) {
                $template->caching = 0;
            } else {
                $template->caching        = 2;
                $template->cache_lifetime = $bcachetime;
            }
            $template->setCompileId($xobject->getVar('dirname', 'n'));
            $tplName = ($tplName = $xobject->getVar('template')) ? "db:$tplName" : 'db:system_block_dummy.tpl';
            $cacheid = $class->generateCacheId('blk_' . $xobject->getVar('bid'));

            $xoopsLogger = XoopsLogger::getInstance();
            if (!$bcachetime || !$template->is_cached($tplName, $cacheid)) {
                $xoopsLogger->addBlock($xobject->getVar('name'));
                $bresult = $xobject->buildBlock();
                if ($bresult) {
                    $template->assign('block', $bresult);
                    $block['content'] = $template->fetch($tplName, $cacheid);
                } else {
                    $block = false;
                }
            } else {
                $xoopsLogger->addBlock($xobject->getVar('name'), true, $bcachetime);
                $block['content'] = $template->fetch($tplName, $cacheid);
            }
            $template->setCompileId();
            $template->assign("xoops_block_{$block['id']}", $block);
            unset($block_arr[$key]);
        }
    }

    /**
     * @return mixed
     */
    public static function isRedirectActive()
    {
        require_once dirname(__DIR__) . '/include/common.php';
        /** @var \XoopsModules\Defacer\Helper $helper */
        $helper = Helper::getInstance();

        return $helper->getConfig('enable_redirect');
    }
}
