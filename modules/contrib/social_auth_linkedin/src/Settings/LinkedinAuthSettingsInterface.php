<?php

namespace Drupal\social_auth_linkedin\Settings;

/**
 * Defines an interface for Social Auth Linkedin settings.
 */
interface LinkedinAuthSettingsInterface {

  /**
   * Gets the client ID.
   *
   * @return string
   *   The client ID.
   */
  public function getClientId();

  /**
   * Gets the client secret.
   *
   * @return string
   *   The client secret.
   */
  public function getClientSecret();

}
