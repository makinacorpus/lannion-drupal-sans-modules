<?php

namespace MakinaCorpus\Lannion\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Vroom vroom.
 *
 * @ConfigEntityType(
 *   id = "voiture_model",
 *   label = @Translation("Car model"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "edit" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "list_builder" = "Drupal\Core\Config\Entity\ConfigEntityListBuilder",
 *   },
 *   admin_permission = "administer content types",
 *   config_prefix = "model",
 *   bundle_of = "voiture",
 *   entity_keys = {
 *     "id" = "type",
 *     "label" = "name",
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/voiture-model/manage/{voiture_model}",
 *     "delete-form" = "/admin/structure/voiture-model/manage/{voiture_model}/delete",
 *     "collection" = "/admin/structure/voiture-model",
 *   },
 *   config_export = {
 *     "name",
 *     "type",
 *   }
 * )
 */
class VoitureModel extends ConfigEntityBundleBase
{
}
