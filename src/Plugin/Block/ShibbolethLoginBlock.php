<?php

namespace Drupal\shibauth8\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a 'ShibbolethLoginBlock' block.
 *
 * @Block(
 *  id = "shibboleth_login_block",
 *  admin_label = @Translation("Shibboleth login block"),
 * )
 */
class ShibbolethLoginBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $config = \Drupal::config('shibauth8.shibbolethsettings');
    $url = Url::fromUri($config->get('shibboleth_login_handler_url'));
    $link_text = $config->get('shibboleth_login_link_text');
    $build['shibboleth_login_block']['#markup'] = Link::fromTextAndUrl(t($link_text), $url)->toString();

    $build['shibboleth_login_block']['#markup'] .= '<br />';

    $host = \Drupal::request()->getHost();
    $url = Url::fromUri('http://' . $host . '/shibauth8/logout');
    $build['shibboleth_login_block']['#markup'] .= Link::fromTextAndUrl(t('Shibboleth Logout'), $url)->toString();

    return $build;
  }

}
