<?php namespace Codersmedia\TrendooSms\Facades;
use Illuminate\Support\Facades\Facade;
class Trendoo extends Facade {
    protected static function getFacadeAccessor() { return 'sms'; }
}
