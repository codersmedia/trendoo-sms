<?php namespace Codersmedia\TrendooSms;

use Illuminate\Support\ServiceProvider;

class TrendooSmsServiceProvider extends ServiceProvider {

	public function boot(){
		$this->handleConfigs();
		$this->handleBinds();
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->make('Codersmedia\TrendooSms\Trendoo');
	}

	private function handleConfigs() {
		$configPath = __DIR__ . '../../config/trendoo.php';
		$this->publishes([$configPath => config_path('trendoo.php')]);
	}

	private function handleBinds(){
		$this->app->bind('sms', function()
		{
			return new \Codersmedia\TrendooSms\Trendoo;
		});
	}

}
