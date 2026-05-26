<?php

namespace modules\contactform\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\web\Controller;
use yii\web\Response;

class ContactController extends Controller
{
  public array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

  public function init(): void
  {
    parent::init();
    $this->enableCsrfValidation = false;
  }

  private function asCorsJson(array $data, int $statusCode = 200): Response
  {
    $response = $this->asJson($data);
    $response->setStatusCode($statusCode);
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
    return $response;
  }

  private function asCorsErrorJson(string $message, int $statusCode = 400): Response
  {
    return $this->asCorsJson(['message' => $message], $statusCode);
  }

  private function saveContactSubmission(?string $name, ?string $email, ?string $subject, ?string $messageText, ?string $unitTag = null): void
  {
    $sectionHandle = App::parseEnv(App::env('CRAFT_CONTACT_FORM_SECTION_HANDLE') ?: 'contactSubmissions');

    try {
      $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
    } catch (\Throwable $e) {
      Craft::error('Error loading contact submission section: ' . $e->getMessage(), __METHOD__);
      return;
    }

    if (!$section) {
      Craft::info('Contact submission section not found: ' . $sectionHandle, __METHOD__);
      return;
    }

    $entryType = $section->getEntryTypes()[0] ?? null;
    if (!$entryType) {
      Craft::error('Contact submission section has no entry type: ' . $sectionHandle, __METHOD__);
      return;
    }

    $entry = new Entry();
    $entry->sectionId = $section->id;
    $entry->typeId = $entryType->id;
    $entry->title = $name !== '' ? $name : 'Contact Submission';
    $entry->enabled = false;
    $entry->setFieldValues([
      $this->contactFieldHandle('Contact Name') => $name,
      $this->contactFieldHandle('Contact Email') => $email,
      $this->contactFieldHandle('Contact Subject') => $subject,
      $this->contactFieldHandle('Contact Message') => $messageText,
      $this->contactFieldHandle('Contact Page') => $unitTag,
      $this->contactFieldHandle('Contact Source IP') => Craft::$app->getRequest()->getUserIP(),
    ]);

    if (!Craft::$app->getElements()->saveElement($entry, false, false)) {
      Craft::error('Unable to save contact submission entry for section: ' . $sectionHandle . '. Errors: ' . print_r($entry->getErrors(), true), __METHOD__);
    } else {
      Craft::info('Contact submission saved to section: ' . $sectionHandle, __METHOD__);
    }
  }

  private function contactFieldHandle(string $label): string
  {
    return StringHelper::toCamelCase($label);
  }

  public function actionSend(): Response
  {
    $request = Craft::$app->getRequest();

    if ($request->getMethod() === 'OPTIONS') {
      return $this->asCorsJson([], 204);
    }

    if (!$request->getIsPost()) {
      return $this->asCorsErrorJson('Only POST requests are allowed.');
    }

    $params = $request->getBodyParams();
    $honeypot = trim((string)($params['your-website'] ?? ''));

    if ($honeypot !== '') {
      return $this->asCorsErrorJson('Unable to submit the form.');
    }

    $name = trim((string)($params['name'] ?? ''));
    $email = trim((string)($params['email'] ?? ''));
    $messageText = trim((string)($params['message'] ?? ''));
    $subject = trim((string)($params['subject'] ?? 'Contact Page Form Entry'));
    $unitTag = trim((string)($params['unitTag'] ?? $params['page'] ?? ''));

    if ($name === '' || $email === '' || $messageText === '') {
      return $this->asCorsErrorJson('Name, email, and message are required.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return $this->asCorsErrorJson('Please enter a valid email address.');
    }

    $this->saveContactSubmission($name, $email, $subject, $messageText, $unitTag);

    $recipient = App::parseEnv(App::env('CRAFT_CONTACT_FORM_RECIPIENT_EMAIL') ?: '');

    if (!$recipient) {
      try {
        $globalSet = Craft::$app->getGlobals()->getSetByHandle('globalOptions');
        if ($globalSet && !empty($globalSet->email)) {
          $recipient = (string)$globalSet->email;
        }
      } catch (\Throwable $e) {
        // ignore fallback
      }
    }

    if (!$recipient) {
      return $this->asCorsErrorJson('No recipient is configured for contact form emails. Please set CRAFT_CONTACT_FORM_RECIPIENT_EMAIL or globalOptions.email.');
    }

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
      return $this->asCorsErrorJson('No valid recipient email is configured.');
    }

    $fromEmail = App::parseEnv(App::env('CRAFT_SMTP_FROM_EMAIL') ?: $recipient);
    $fromName = App::parseEnv(App::env('CRAFT_SMTP_FROM_NAME') ?: 'Website Contact Form');
    $smtpHost = App::env('CRAFT_SMTP_HOST') ?: App::env('MAILPIT_SMTP_HOSTNAME') ?: '127.0.0.1';
    $smtpPort = App::env('CRAFT_SMTP_PORT') ?: App::env('MAILPIT_SMTP_PORT') ?: 1025;

    Craft::info("Contact form sending email to {$recipient} from {$fromEmail} via {$smtpHost}:{$smtpPort}", __METHOD__);

    $mailer = Craft::$app->getMailer();
    $message = $mailer->compose()
      ->setFrom([$fromEmail => $fromName])
      ->setReplyTo($email)
      ->setTo($recipient)
      ->setSubject($subject)
      ->setTextBody("Name: {$name}\nEmail: {$email}\n\nMessage:\n{$messageText}")
      ->setHtmlBody(
        '<p><strong>Name:</strong> ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' .
          '<p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' .
          '<p><strong>Message:</strong></p><p>' . nl2br(htmlspecialchars($messageText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>'
      );

    try {
      if (!$mailer->send($message)) {
        Craft::error('Contact form mailer returned false for send()', __METHOD__);
        return $this->asCorsErrorJson('Unable to send email. Please try again later.');
      }

      return $this->asCorsJson([
        'status' => 'mail_sent',
        'message' => 'Your message has been sent successfully.',
      ]);
    } catch (\Throwable $e) {
      Craft::error('Contact form send failed: ' . $e->getMessage(), __METHOD__);
      return $this->asCorsErrorJson('Unable to send email. Please try again later.');
    }
  }
}
