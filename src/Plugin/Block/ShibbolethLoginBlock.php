<?php

/**
 * @file
 * Contains ShibbolethLoginBlock class to define block.
 */

namespace Drupal\shibauth8\Plugin\Block;

use Drupal\Core\Block\BlockBase;

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

    $config = \Drupal::config('shibauth8.advancedsettings');
    $current_user = \Drupal::currentUser();

    $markup = '<div class="shibboleth-links">';
    if (!$current_user->id()) {
      $markup .= '<div class="shibboleth-login">' . shibauth8_get_login_link() . '</div>';
    }
    else {
      $markup .= '<div class="shibboleth-logout">' . shibauth8_get_logout_link() . '</div>';
    }
    $markup .= '</div>';

    $build['shibboleth_login_block'] = [
      '#markup' => $markup,
      '#cache' => [
        'contexts' => [
          'user.roles:anonymous',
        ],
      ],
    ];

    if (!$config->get('url_redirect_login')) {
      // Redirect is not set, so it will use the current path. That means it
      // will differ per page.
      $build['shibboleth_login_block']['#cache']['contexts'][] = 'url.path';
      $build['shibboleth_login_block']['#cache']['contexts'][] = 'url.query_args';
    }

    return $build;

  }

}
