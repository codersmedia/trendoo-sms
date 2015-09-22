<?php

namespace Codersmedia\TrendooSms;

use Illuminate\Support\Facades\Facade;

class TrendooFacade extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sms';
    }
}
