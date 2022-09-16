<?php

namespace Drupal\layout_paragraphs_templates\Controller;

use Drupal\node\Entity\Node;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_paragraphs\Utility\Dialog;
use Symfony\Component\HttpFoundation\Request;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsComponent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;

/**
 * Defines a Template Controller class for working with templates.
 */
class TemplateController extends ControllerBase {

  /**
   * The tempstore service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    LayoutParagraphsLayoutTempstoreRepository $tempstore
    ) {
    $this->tempstore = $tempstore;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository')
    );
  }

  /**
   * Inserts content from the provided template.
   */
  public function insert(Request $request, LayoutParagraphsLayout $layout_paragraphs_layout, Node $node) {
    $parent_uuid = $request->query->get('parent_uuid');
    $region = $request->query->get('region');
    $sibling_uuid = $request->query->get('sibling_uuid');
    $placement = $request->query->get('placement');
    $source_components = $this->cloneList($node->field_lp_template_content->referencedEntities());
    $paragraph_reference_field = $layout_paragraphs_layout->getParagraphsReferenceField();
    $list = $paragraph_reference_field->getValue();

    if ($sibling_uuid && $placement) {
      $delta = -1;
      foreach ($paragraph_reference_field as $key => $item) {
        if (isset($item->entity) && $item->entity->uuid() == $sibling_uuid) {
          $delta = $key;
          break;
        }
      }
      $delta += ($placement == 'before' ? 0 : 1);
      foreach ($source_components as $source_component) {
        array_splice($list, $delta, 0, ['entity' => $source_component]);
        $delta++;
      }
    }
    else {
      $delta = 0;
      foreach ($source_components as $source_component) {
        array_splice($list, $delta, 0, ['entity' => $source_component]);
        $delta++;
      }
    }

    $paragraph_reference_field->setValue($list);
    $layout_paragraphs_layout->setParagraphsReferenceField($paragraph_reference_field);
    $this->tempstore->set($layout_paragraphs_layout);
    $response = new AjaxResponse();
    $dom_selector = '[data-lpb-id="' . $layout_paragraphs_layout->id() . '"]';
    $dialog_selector = Dialog::dialogSelector($layout_paragraphs_layout);
    $response->addCommand(new CloseDialogCommand($dialog_selector));
    $response->addCommand(new ReplaceCommand($dom_selector, [
      '#type' => 'layout_paragraphs_builder',
      '#layout_paragraphs_layout' => $layout_paragraphs_layout,
    ]));
    return $response;
  }

  /**
   * Clones an array of paragraph components, correctly mapping parent uuids.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph[] $list
   *   An array of paragraphs to clone.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph[]
   *   The cloned array with new parent uuids correctly mapped.
   */
  protected function cloneList(array $list) {
    foreach ($list as $delta => $item) {
      $uuid_map[$item->uuid()] = $delta;
      $cloned[$delta] = $item->createDuplicate();
      $settings = $cloned[$delta]->getAllBehaviorSettings()['layout_paragraphs'];
      if ($old_parent_uuid = $settings['parent_uuid']) {
        $settings['parent_uuid'] = $cloned[$uuid_map[$old_parent_uuid]]->uuid();
        $cloned[$delta]->setBehaviorSettings('layout_paragraphs', $settings);
      }
    }
    return $cloned;
  }

}
