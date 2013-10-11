<?php
/**
 * modify dom before outputting to client
 * @src http://stackoverflow.com/questions/772510/wordpress-filter-to-modify-final-html-output
 * @src http://www.dagondesign.com/articles/wordpress-hook-for-entire-page-using-output-buffering/
 * 
 * @author gwillie <gwseclists@gmail.com>
 * @version 0.1.0
 * @package WordPress
 * @subpackage Output
 * @copyright (c) 2013, gwillie
 * @license http://www.gnu.org/licenses/gpl-2.0.txt‎ GPLv2
 * 
 */

/**
 * WP_Renderer allows one last chance to manipulate the majority of the dom
 * before output to client. You can't instaniate WP_Renderer, constructor is
 * private for a reason. Use add_renderer() and remove_renderer() instead.
 * 
 * 
 * NOTE:
 * -----
 * 
 * On a normal wp request to admin pages, with no markup except menus, a normal
 * wp page is about 20kb. Manipulating dom with regexes or DomDocument is going
 * to be expensive, use sparingly when no other way is possible.
 * 
 * Written for WordPress 3.6.1
 * 
 * 
 * How To Use:
 * -----------
 * 
 * There is no way to instantiate WP_Renderer. It is a static class. It works
 * for frontend and admin areas. You can access most of the dom. You can use
 * add_renderer() in 'admin' or 'front' hooks, and only renderers that need to run
 * will run. The add_renderer() and remove_renderer() functions are similar in
 * usage to add_action() and remove_action() functions, eg:
 * 
 *   add_renderer($hook, $callback, $priority);
 * 
 *   add_renderer('admin', 'renderer_callback', 3);
 * 
 *   add_renderer('front', array($obj, 'renderer_callback'));
 * 
 *   add_renderer('front', 'Some_Class::renderer_callback', 99);
 * 
 * The callback must take one param, the $html that will be passed to it, and
 * the callback must return $html.
 * 
 * WP_Renderer uses wordpress hooks to start/flush output buffering,
 * thereby not allowing complete access to the dom. There're 2 WP_Renderer hooks
 * 
 * admin: accesses from enqueued scripts until just before the closing tag of
 *        div#wpwrap, therefore missing closing tags for div#wpwrap and body
 * front: accesses from enqueued scripts until just before the closing tag of
 *        div#wpwrap, therefore missing closing tags for div#wpwrap and body
 * 
 * 
 * Properties
 * ----------
 * 
 * @param array WP_Renderer::$renderers public access if you need to fiddle with $renderers
 * 
 */
class WP_Renderer
{
  /**
   * array of renderer handlers. $renderers = [0 => hook, 1 => callback, 2 => priority]
   * @var Array 
   * @access public
   */
  public static $renderers = array();
  /**
   * @var string current request, either 'admin' or 'front'
   */
  private static $hook = '';

  /**
   * callback for ob_start(), called from self::init()
   * 
   * @param string $html html string passed by ob_start
   * @return string html string after run through renderers
   */
  public static function do_renderer($html)
  {
    if(self::$renderers){
      // sort renderers of current hook into priority array
      $priority = array();
      foreach(self::$renderers as $key => $args)
        if($args[0] == self::$hook)
          $priority[$args[2]][] = $args[1];

      
      // call each renderer handler
      if($priority){
        ksort($priority, SORT_NUMERIC);
        foreach($priority as $renderers)
          foreach($renderers as $renderer)
            if(is_callable($renderer))
              $html = call_user_func($renderer, $html);
      }
    }
    
    return $html;
  }

/**
 * initalise WP_Renderer
 * 
 * @staticvar boolean $inited run method once var
 * @return void
 */
  public static function init()
  {
    static $inited = false;
    if(!$inited){
      $inited = true;
      self::$hook = is_admin() ? 'admin' : 'front';
      
      // register output buffereing start and flush hooks
      add_action(self::$hook == 'admin' ? 'admin_enqueue_scripts' : 'wp_enqueue_scripts', __CLASS__ . '::ob_start', -9999);
      add_action(self::$hook == 'admin' ? 'admin_print_footer_scripts' : 'wp_print_footer_scripts', __CLASS__ . '::ob_flush',  9999);
    }
  }

  /**
   * @access private Absolutely Static
   */
  private final function __construct(){}
  public static function ob_start(){ob_start(__CLASS__ . '::do_renderer');}
  public static function ob_flush(){ob_end_flush();}
  
} // END class

// init
WP_Renderer::init();

/**
 * register a renderer
 * 
 * @param string $hook where renderer is applied. accepted values: 'admin' and 'front'
 * @param mixed $callback any valid callback, string, object or static method
 * @param int $priority Lower numbers correspond with earlier execution, and functions with the same priority are executed in the order in which they were added
 * @return int returns array index to use with remove_renderer()
 */
function add_renderer($hook, $callback, $priority = 10)
{
  WP_Renderer::$renderers[] = array($hook, $callback, (int)$priority);
  end(WP_Renderer::$renderers);
  return key(WP_Renderer::$renderers);
}

/**
 * function for removing a renderer
 * 
 * @param int $index index to remove, returned by add_renderer when registering
 * @return boolean True on success, false on failure
 */
function remove_renderer($index)
{
  if(isset(WP_Renderer::$renderers[(int)$index])){
    unset(WP_Renderer::$renderers[(int)$index]);
    return true;
  }
  return false;
}
