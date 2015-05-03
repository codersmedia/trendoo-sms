<?php namespace Codersmedia\TrendooSms;

use Illuminate\Support\ServiceProvider;

class TrendooSmsServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;


	public function boot(){

		/*$loader  = AliasLoader::getInstance();
        	$aliases = Config::get('app.aliases');
        	
		if (empty($aliases['SMS'])) {
            		$loader->alias('SMS', 'Codersmedia\TrendooSms\Facades\Trendoo');
        	}*/

		$this->publishes([
           		 __DIR__.'/config/trendoo.php' => config_path('trendoo.php'),
        	]);
	}
	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		\App::bind('sms', function()
		{
    			return new \Codersmedia\TrendooSms\Trendoo;
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return ['sms'];
	}

}
