<?php

namespace Drupal\quick_node_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;

/**
 * Provides a Node Block with his display.
 *
 * @Block(
 *   id = "quick_node_block",
 *   admin_label = @Translation("Quick Node Block"),
 *   category = @Translation("Quick Node Block"),
 * )
 */
class QuickNodeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplay;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('entity_type.manager'),
        $container->get('entity.manager'),
        $container->get('entity_display.repository')
      );
  }

  /**
   * This function construct a block.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   The entity manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplay
   *   The entity display repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, EntityManagerInterface $entityManager, EntityDisplayRepositoryInterface $entityDisplay) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityManager = $entityManager;
    $this->entityDisplay = $entityDisplay;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['quick_node'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Node'),
      '#description' => $this->t('What node do you want to show? You can write a node number or node title.'),
      '#required' => TRUE,
      '#autocomplete_route_name' => 'quick_node_block.autocomplete',
      '#default_value' => $config['quick_node'],
    ];

    $form['quick_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display'),
      '#description' => $this->t('How do you want the node to be displayed?. If display mode not exist, it will be like default.'),
      '#required' => TRUE,
      '#options' => $this->getQuickDisplays(),
      '#default_value' => $config['quick_display'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['quick_node'] = $values['quick_node'];
    $this->configuration['quick_display'] = $values['quick_display'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $config = $this->getConfiguration();
    if (preg_match("/.+\s\(([^\)]+)\)/", $config['quick_node'], $matches)) {
      $nid = $matches[1];
      $view_mode = $config['quick_display'];
      $entity_type = 'node';
      $view_builder = $this->entityTypeManager->getViewBuilder($entity_type);
      $storage = $this->entityManager->getStorage($entity_type);
      if ($node = $storage->load($nid)) {
        $build = $view_builder->view($node, $view_mode);
      }
    }
    return $build;
  }

  /**
   * Show all display modes of content.
   */
  protected function getQuickDisplays() {
    return $this->entityDisplay->getViewModeOptions('node');

  }

}
