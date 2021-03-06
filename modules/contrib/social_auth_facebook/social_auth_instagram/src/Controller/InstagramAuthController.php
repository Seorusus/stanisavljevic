<?php

namespace Drupal\social_auth_instagram\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth_instagram\InstagramAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Social Auth Instagram routes.
 */
class InstagramAuthController extends ControllerBase {

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The user manager.
   *
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  private $userManager;

  /**
   * The instagram authentication manager.
   *
   * @var \Drupal\social_auth_instagram\InstagramAuthManager
   */
  private $instagramManager;

  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $request;

  /**
   * The Social Auth Data Handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  private $dataHandler;


  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * InstagramAuthController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_instagram network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_auth_instagram\InstagramAuthManager $instagram_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   SocialAuthDataHandler object.
   */
  public function __construct(NetworkManager $network_manager,
                              SocialAuthUserManager $user_manager,
                              InstagramAuthManager $instagram_manager,
                              RequestStack $request,
                              SocialAuthDataHandler $data_handler) {

    $this->networkManager = $network_manager;
    $this->userManager = $user_manager;
    $this->instagramManager = $instagram_manager;
    $this->request = $request;
    $this->dataHandler = $data_handler;

    // Sets the plugin id.
    $this->userManager->setPluginId('social_auth_instagram');

    // Sets the session keys to nullify if user could not logged in.
    $this->userManager->setSessionKeysToNullify(['access_token', 'oauth2state']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_manager'),
      $container->get('social_auth_instagram.manager'),
      $container->get('request_stack'),
      $container->get('social_auth.data_handler')
    );
  }

  /**
   * Response for path 'user/login/instagram'.
   *
   * Redirects the user to Instagram for authentication.
   */
  public function redirectToInstagram() {
    /* @var \League\OAuth2\Client\Provider\Instagram|false $instagram */
    $instagram = $this->networkManager->createInstance('social_auth_instagram')->getSdk();

    // If instagram client could not be obtained.
    if (!$instagram) {
      drupal_set_message($this->t('Social Auth Instagram not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Instagram service was returned, inject it to $instagramManager.
    $this->instagramManager->setClient($instagram);

    // Generates the URL where the user will be redirected for Instagram login.
    $instagram_login_url = $this->instagramManager->getAuthorizationUrl();

    $state = $this->instagramManager->getState();

    $this->dataHandler->set('oauth2state', $state);

    return new TrustedRedirectResponse($instagram_login_url);
  }

  /**
   * Response for path 'user/login/instagram/callback'.
   *
   * Instagram returns the user here after user has authenticated in Instagram.
   */
  public function callback() {

    // Checks if user cancel login.
    $error = $this->request->getCurrentRequest()->get('error');
    if ($error == 'access_denied') {
      drupal_set_message($this->t('You could not be authenticated.'), 'error');
      return $this->redirect('user.login');
    }

    /* @var \League\OAuth2\Client\Provider\Instagram false $instagram */
    $instagram = $this->networkManager->createInstance('social_auth_instagram')->getSdk();

    // If Instagram client could not be obtained.
    if (!$instagram) {
      drupal_set_message($this->t('Social Auth Instagram not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oauth2state');

    // Retreives $_GET['state'].
    $retrievedState = $this->request->getCurrentRequest()->query->get('state');
    if (empty($retrievedState) || ($retrievedState !== $state)) {
      $this->userManager->nullifySessionKeys();
      drupal_set_message($this->t('Instagram login failed. Unvalid OAuth2 State.'), 'error');
      return $this->redirect('user.login');
    }

    $this->instagramManager->setClient($instagram)->authenticate();

    // Saves access token to session.
    $this->dataHandler->set('access_token', $this->instagramManager->getAccessToken());

    // Gets user's info from Instagram API.
    /* @var \League\OAuth2\Client\Provider\InstagramResourceOwner $instagram_profile */
    if (!$instagram_profile = $this->instagramManager->getUserInfo()) {
      drupal_set_message($this->t('Instagram login failed, could not load Instagram profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $data = $this->userManager->checkIfUserExists($instagram_profile->getId()) ? NULL : $this->instagramManager->getExtraDetails();

    // If user information could be retrieved.
    return $this->userManager->authenticateUser($instagram_profile->getName(), '', $instagram_profile->getId(), $this->instagramManager->getAccessToken(), $instagram_profile->getImageurl(), $data);

  }

}
