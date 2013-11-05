<?php namespace Orchestra\Debug;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DebugServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var boolean
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerProfiler();
        $this->registerEvents();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    protected function registerProfiler()
    {
        $this->app['orchestra.debug'] = $this->app->share(function ($app) {
            $profiler = new Profiler($app, $app['log']->getMonolog());

            $profiler->setEventDispatcher($app['events']);

            return $profiler;
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    protected function registerEvents()
    {
        $me = $this;

        $this->app['events']->listen('orchestra.debug: attaching', function ($monolog) use ($me) {
            foreach (array('Database', 'NotFoundException', 'Request') as $event) {
                call_user_func(array($me, "register{$event}Logger"), $monolog);
            }
        });
    }

    /**
     * Register the database query listener.
     *
     * @param  \Monolog\Logger  $monolog
     * @return void
     */
    public function registerDatabaseLogger($monolog)
    {
        $db = $this->app['db'];

        $this->app['events']->listen('illuminate.query', function ($sql, $bindings, $time) use ($db, $monolog) {
            $sql = str_replace_array('\?', $db->prepareBindings($bindings), $sql);

            $monolog->addInfo('<comment>'.$sql.' ['.$time.'ms]</comment>');
        });
    }

    /**
     * Register the not found exception logger event.
     *
     * @param  \Monolog\Logger  $monolog
     * @return void
     */
    public function registerNotFoundExceptionLogger($monolog)
    {
        $request = $this->app['request'];

        $this->app->error(function (NotFoundHttpException $e) use ($monolog, $request) {
            $monolog->addInfo('<error>'.strtolower($request->getMethod()).' '.$request->path().'</error>');
        });
    }

    /**
    * Register the request logger event.
    *
    * @param  \Monolog\Logger  $monolog
    * @return void
    */
    public function registerRequestLogger($monolog)
    {
        $request = $this->app['request'];

        $monolog->addInfo('<info>'.strtolower($request->getMethod()).' '.$request->path().'</info>');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('orchestra.debug');
    }
}
