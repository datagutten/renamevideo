<?php


namespace datagutten\renamevideo;


use Twig;

class utils
{
    /**
     * @var Twig\Environment
     */
    public $twig;
    public $root = '/renamevideo';

    function __construct()
    {
        $loader = new Twig\Loader\FilesystemLoader(array(__DIR__.'/../templates'), __DIR__);
        $this->twig = new Twig\Environment($loader, array('debug' => true, 'strict_variables' => true));
    }

    /**
     * Renders a template.
     *
     * @param string $name    The template name
     * @param array  $context An array of parameters to pass to the template
     *
     * @return string The rendered template
     *
     */
    public function render($name, $context)
    {
        $context = array_merge($context, array('root'=>$this->root));
        try {
            return $this->twig->render($name, $context);
        }
        catch (Twig\Error\Error $e)
        {
            $msg = "Error rendering template:\n" . $e->getMessage();
            try {
                die($this->twig->render('error.twig', array(
                        'root'=>$this->root,
                        'title'=>'Rendering error',
                        'error'=>$msg,
                        'trace'=>$e->getTraceAsString())
                ));
            }
            catch (Twig\Error\Error $e_e)
            {
                $msg = sprintf("Original error: %s\n<pre>%s</pre>\nError rendering error template: %s\n<pre>%s</pre>",
                    $e->getMessage(), $e->getTraceAsString(), $e_e->getMessage(), $e_e->getTraceAsString());
                die($msg);
            }
        }
    }
}
