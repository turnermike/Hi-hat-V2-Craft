<?php

namespace modules\contactform;

use yii\base\Module;

class ContactFormModule extends Module
{
  public function init(): void
  {
    parent::init();
    $this->controllerNamespace = 'modules\\contactform\\controllers';
  }
}
