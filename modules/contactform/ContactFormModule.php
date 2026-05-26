<?php

namespace modules\contactform;

use Craft;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\fields\Email;
use craft\fields\PlainText;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\services\Fields;
use yii\base\Module as YiiModule;

class ContactFormModule extends YiiModule
{
  public function init(): void
  {
    parent::init();
    $this->controllerNamespace = 'modules\\contactform\\controllers';
  }

  public function ensureContactSubmissionSchema(): bool
  {
    $fieldsService = Craft::$app->getFields();
    $entriesService = Craft::$app->getEntries();
    $sitesService = Craft::$app->getSites();

    $fields = [
      'contactName' => $this->ensureField($fieldsService, PlainText::class, 'Contact Name', [
        'multiline' => false,
      ]),
      'contactEmail' => $this->ensureField($fieldsService, Email::class, 'Contact Email', []),
      'contactSubject' => $this->ensureField($fieldsService, PlainText::class, 'Contact Subject', [
        'multiline' => false,
      ]),
      'contactMessage' => $this->ensureField($fieldsService, PlainText::class, 'Contact Message', [
        'multiline' => true,
      ]),
      'contactPage' => $this->ensureField($fieldsService, PlainText::class, 'Contact Page', [
        'multiline' => false,
      ]),
      'contactSourceIp' => $this->ensureField($fieldsService, PlainText::class, 'Contact Source IP', [
        'multiline' => false,
      ]),
    ];

    foreach ($fields as $fieldHandle => $field) {
      if (!$field) {
        Craft::error('Unable to create or load field: ' . $fieldHandle, __METHOD__);
        return false;
      }
    }

    $sectionHandle = App::parseEnv(App::env('CRAFT_CONTACT_FORM_SECTION_HANDLE') ?: 'contactSubmissions');
    $section = $entriesService->getSectionByHandle($sectionHandle);
    if (!$section) {
      $section = new Section();
      $section->name = 'Contact Submissions';
      $section->handle = $sectionHandle;
      $section->type = Section::TYPE_CHANNEL;
      $section->propagationMethod = \craft\enums\PropagationMethod::All;
      $section->enableVersioning = false;
      $section->maxLevels = 1;
    }

    $siteSettings = [];
    foreach ($sitesService->getAllSites() as $site) {
      $siteSettings[] = new Section_SiteSettings([
        'siteId' => $site->id,
        'enabledByDefault' => false,
        'hasUrls' => false,
      ]);
    }
    $section->setSiteSettings($siteSettings);

    $entryType = null;
    foreach ($entriesService->getEntryTypesBySectionId($section->id ?? 0) as $existingEntryType) {
      $entryType = $existingEntryType;
      break;
    }

    if (!$entryType) {
      $entryType = new EntryType();
      $entryType->uid = StringHelper::UUID();
      $entryType->name = 'Contact Submission';
      $entryType->handle = 'contactSubmission';
      $entryType->hasTitleField = true;
      $entryType->titleFormat = '{contactName} - {dateCreated}';
    }

    $fieldLayout = FieldLayout::createFromConfig([
      'type' => Entry::class,
      'tabs' => [
        [
          'name' => 'Content',
          'fields' => array_fill_keys(array_map(fn(FieldInterface $field) => $field->uid, $fields), ['required' => false]),
        ],
      ],
    ]);

    $entryType->setFieldLayout($fieldLayout);

    if (!$entryType->id) {
      if (!$entriesService->saveEntryType($entryType)) {
        Craft::error('Unable to save contact submission entry type: ' . $entryType->handle, __METHOD__);
        return false;
      }
    }

    $section->setEntryTypes([$entryType]);

    if (!$entriesService->saveSection($section)) {
      Craft::error('Unable to save contact submission section: ' . $sectionHandle, __METHOD__);
      return false;
    }

    return true;
  }

  private function ensureField(Fields $fieldsService, string $fieldClass, string $name, array $settings): ?FieldInterface
  {
    $handle = StringHelper::toCamelCase($name);
    $field = $fieldsService->getFieldByHandle($handle);
    if ($field) {
      return $field;
    }

    /** @var FieldInterface $field */
    $field = new $fieldClass();
    $field->context = 'global';
    $field->name = $name;
    $field->handle = $handle;

    foreach ($settings as $key => $value) {
      $field->$key = $value;
    }

    return $fieldsService->saveField($field) ? $field : null;
  }
}
