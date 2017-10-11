<?php

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

    return $build;

  }

}
