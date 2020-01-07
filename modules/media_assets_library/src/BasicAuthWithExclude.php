<?php

namespace Drupal\media_assets_library;

use Drupal;
use Drupal\basic_auth\Authentication\Provider\BasicAuth;
use Symfony\Component\HttpFoundation\Request;

/**
 * Exclude HTTP Basic authentication.
 */
class BasicAuthWithExclude extends BasicAuth {

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Enable Basic Auth only on the jsonapi routes.
    if (Drupal::routeMatch()->getRouteObject() && !Drupal::routeMatch()->getRouteObject()->getOption('_is_jsonapi')) {
      $applies = FALSE;
    }
    else {
      $applies = parent::applies($request);
    }

    return $applies;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    // Run authenticate only for non LDAP users.
    if (Drupal::moduleHandler()->moduleExists('ldap_user')) {
      $username = $request->headers->get('PHP_AUTH_USER');
      $accounts = $this->entityManager->getStorage('user')->loadByProperties(['name' => $username, 'status' => 1]);
      $account = reset($accounts);
      if ($account && !$account->get('ldap_user_puid')->value) {
        return parent::authenticate($request);
      }
    }
    else {
      return parent::authenticate($request);
    }

    return [];
  }

}
