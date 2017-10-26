<?php

namespace MakinaCorpus\Lannion\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Vroom vroom.
 *
 * @ContentEntityType(
 *   id = "voiture",
 *   label = @Translation("Car"),
 *   label_collection = @Translation("Car"),
 *   label_singular = @Translation("Car"),
 *   label_plural = @Translation("Cars"),
 *   label_count = @PluralTranslation(
 *     singular = "@count car",
 *     plural = "@count cars"
 *   ),
 *   bundle_label = @Translation("Model"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm"
 *     },
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler"
 *   },
 *   base_table = "lannion_voiture",
 *   data_table = "lannion_voiture_field_data",
 *   revision_table = "lannion_voiture_revision",
 *   revision_data_table = "lannion_voiture_field_revision",
 *   show_revision_ui = FALSE,
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "bundle" = "model",
 *     "label" = "title",
 *     "langcode" = "language",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "uid" = "user_id",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user_id",
 *     "revision_created" = "revision_created_at",
 *   },
 *   bundle_entity_type = "voiture_model",
 *   field_ui_base_route = "entity.voiture_model.edit_form",
 *   common_reference_target = TRUE,
 *   permission_granularity = "bundle",
 *   links = {
 *     "canonical" = "/voiture/{voiture}",
 *     "delete-form" = "/voiture/{voiture}/delete",
 *     "edit-form" = "/voiture/{voiture}/edit",
 *     "create" = "/voiture/add",
 *   }
 * )
 */
class Voiture extends EditorialContentEntityBase
{
    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entityType)
    {
        $fields = parent::baseFieldDefinitions($entityType);

        $fields['title'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Title'))
            ->setRequired(true)
            ->setTranslatable(true)
            ->setRevisionable(true)
            ->setSetting('max_length', 255)
            ->setDisplayOptions('view', ['label' => 'hidden', 'type' => 'string', 'weight' => -5])
            ->setDisplayConfigurable('form', true)
        ;

        $fields['uid'] = BaseFieldDefinition::create('entity_reference')
            ->setLabel(t('Authored by'))
            ->setRevisionable(true)
            ->setSetting('target_type', 'user')
            ->setDisplayOptions('view', ['label' => 'hidden', 'type' => 'author', 'weight' => 0])
            ->setDisplayConfigurable('form', true)
        ;

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Authored on'))
            ->setRevisionable(true)
            ->setDisplayOptions('view', ['label' => 'hidden', 'type' => 'timestamp', 'weight' => 0])
            ->setDisplayConfigurable('form', true)
        ;

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setRevisionable(true)
            ->setTranslatable(true)
            ->setDisplayConfigurable('form', true)
        ;

        return $fields;
    }
}
