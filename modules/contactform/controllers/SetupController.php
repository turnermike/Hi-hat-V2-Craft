<?php

namespace modules\contactform\controllers;

use Craft;
use yii\console\Controller;
use yii\console\ExitCode;

class SetupController extends Controller
{
  public function actionIndex(): int
  {
    $module = Craft::$app->getModule('contactform');
    if (!$module) {
      $this->stderr("Contact form module is not loaded.\n");
      return ExitCode::UNSPECIFIED_ERROR;
    }

    if (!method_exists($module, 'ensureContactSubmissionSchema')) {
      $this->stderr("Contact form module does not support schema setup.\n");
      return ExitCode::UNSPECIFIED_ERROR;
    }

    $this->stdout("Ensuring contact submission section and fields...\n");

    if ($module->ensureContactSubmissionSchema()) {
      $this->stdout("Contact submissions schema has been created or updated successfully.\n");
      return ExitCode::OK;
    }

    $this->stderr("Unable to create or update the contact submissions schema. Check logs for details.\n");
    return ExitCode::UNSPECIFIED_ERROR;
  }
}
