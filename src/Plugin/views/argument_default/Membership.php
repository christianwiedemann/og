<?php

namespace Drupal\og\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Drupal\og\MembershipManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin to provide the group from the current context.
 *
 * @ViewsArgumentDefault(
 *   id = "og_group_membership",
 *   title = @Translation("Group memberships from current user")
 * )
 */
class Membership extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The OG context provider.
   *
   * @var \Drupal\Core\Plugin\Context\ContextProviderInterface
   */
  protected $ogContext;

  /**
   * The OG membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected $ogMembership;

  /**
   * The user to be evaluated.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $ogUser;

  /**
   * Constructs a new User instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Plugin\Context\ContextProviderInterface $og_context
   *   The OG context provider.
   * @param \Drupal\og\MembershipManagerInterface $og_membership
   *   The OG membership manager.
   * @param \Drupal\Core\Session\AccountInterface $og_user
   *   The user to be evaluated.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContextProviderInterface $og_context, MembershipManagerInterface $og_membership, AccountInterface $og_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->ogContext = $og_context;
    $this->ogMembership = $og_membership;
    $this->ogUser = $og_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('og.context'),
      $container->get('og.membership_manager'),
      $container->get('current_user')->getAccount()
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['og_group_membership'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['og_group_membership'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    // Currently restricted to node entities.
    return implode(',', $this->getCurrentUserGroupIds('node'));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['og_role'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $group = $this->getGroup();
    if ($group instanceof ContentEntityInterface) {
      $tag = $group->getEntityTypeId() . ':' . $group->id();
      return Cache::buildTags('og-group-content', [$tag]);
    }
    return [];
  }

  /**
   * Returns groups that current user is a member of.
   *
   * @return array
   *   An array of groups, or an empty array if no group is found.
   */
  protected function getCurrentUserGroupIds($entity_type = 'node') {
    $groups = $this->ogMembership->getUserGroupIds($this->ogUser);
    if (!empty($groups) && isset($groups[$entity_type])) {
      return $groups[$entity_type];
    }
    return [];
  }

  /**
   * Returns the group from the runtime context.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The group from context if found.
   */
  protected function getGroup() {
    $contexts = $this->ogContext->getRuntimeContexts(['og']);
    if (!empty($contexts['og']) && $group = $contexts['og']->getContextValue()) {
      if ($group instanceof ContentEntityInterface) {
        return $group;
      }
    }
  }

}
