<?php

namespace Drupal\social_auth_linkedin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth_linkedin\LinkedinAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Returns responses for Simple Linkedin Connect module routes.
 */
class LinkedinAuthController extends ControllerBase {

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
   * The linkedin authentication manager.
   *
   * @var \Drupal\social_auth_linkedin\LinkedinAuthManager
   */
  private $linkedinManager;

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
   * LinkedinAuthController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_linkedin network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_auth_linkedin\LinkedinAuthManager $linkedin_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_auth\SocialAuthDataHandler $social_auth_data_handler
   *   SocialAuthDataHandler object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   */
  public function __construct(NetworkManager $network_manager, SocialAuthUserManager $user_manager, LinkedinAuthManager $linkedin_manager, RequestStack $request, SocialAuthDataHandler $social_auth_data_handler, LoggerChannelFactoryInterface $logger_factory) {

    $this->networkManager = $network_manager;
    $this->userManager = $user_manager;
    $this->linkedinManager = $linkedin_manager;
    $this->request = $request;
    $this->dataHandler = $social_auth_data_handler;
    $this->loggerFactory = $logger_factory;

    // Sets the plugin id.
    $this->userManager->setPluginId('social_auth_linkedin');

    // Sets the session keys to nullify if user could not logged in.
    $this->userManager->setSessionKeysToNullify(['access_token', 'oauth2state']);
    $this->setting = $this->config('social_auth_linkedin.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_manager'),
      $container->get('social_auth_linkedin.manager'),
      $container->get('request_stack'),
      $container->get('social_auth.social_auth_data_handler'),
      $container->get('logger.factory')
    );
  }

  /**
   * Response for path 'user/login/linkedin'.
   *
   * Redirects the user to Linkedin for authentication.
   */
  public function redirectToLinkedin() {
    /* @var \League\OAuth2\Client\Provider\Linkedin false $linkedin */
    $linkedin = $this->networkManager->createInstance('social_auth_linkedin')->getSdk();

    // If linkedin client could not be obtained.
    if (!$linkedin) {
      drupal_set_message($this->t('Social Auth Linkedin not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Linkedin service was returned, inject it to $linkedinManager.
    $this->linkedinManager->setClient($linkedin);

    // Generates the URL where the user will be redirected for Linkedin login.
    // If the user did not have email permission granted on previous attempt,
    // we use the re-request URL requesting only the email address.
    $linkedin_login_url = $this->linkedinManager->getLinkedinLoginUrl();

    $state = $this->linkedinManager->getState();

    $this->dataHandler->set('oauth2state', $state);

    return new TrustedRedirectResponse($linkedin_login_url);
  }

  /**
   * Response for path 'user/login/linkedin/callback'.
   *
   * Linkedin returns the user here after user has authenticated in Linkedin.
   */
  public function callback() {
    // Checks if user cancel login via Linkedin.
    $error = $this->request->getCurrentRequest()->get('error');
    if ($error == 'access_denied') {
      drupal_set_message($this->t('You could not be authenticated.'), 'error');
      return $this->redirect('user.login');
    }

    /* @var \League\OAuth2\Client\Provider\Linkedin false $linkedin */
    $linkedin = $this->networkManager->createInstance('social_auth_linkedin')->getSdk();

    // If Linkedin client could not be obtained.
    if (!$linkedin) {
      drupal_set_message($this->t('Social Auth Linkedin not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oauth2state');

    // Retreives $_GET['state'].
    $retrievedState = $this->request->getCurrentRequest()->query->get('state');
    if (empty($retrievedState) || ($retrievedState !== $state)) {
      $this->userManager->nullifySessionKeys();
      drupal_set_message($this->t('Linkedin login failed. Unvalid OAuth2 State.'), 'error');
      return $this->redirect('user.login');
    }

    // Saves access token to session.
    $this->dataHandler->set('access_token', $this->linkedinManager->getAccessToken());

    $this->linkedinManager->setClient($linkedin)->authenticate();

    // Gets user's info from Linkedin API.
    if (!$linkedin_profile = $this->linkedinManager->getUserInfo()) {
      drupal_set_message($this->t('Linkedin login failed, could not load Linkedin profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Store the data mapped with data points define is
    // social_auth_linkedin settings.
    $data = $linkedin_profile->toArray();

    if (!$this->userManager->checkIfUserExists($linkedin_profile->getId())) {
      $api_calls = explode(PHP_EOL, $this->linkedinManager->getApiCalls());

      // Iterate through api calls define in settings and try to retrieve them.
      foreach ($api_calls as $api_call) {
        $call = $this->linkedinManager->getExtraDetails($api_call);
        array_push($data, $call);
      }
    }
    // If user information could be retrieved.
    return $this->userManager->authenticateUser($linkedin_profile->getFirstName() . ' ' . $linkedin_profile->getLastName(), $linkedin_profile->getEmail(), $linkedin_profile->getId(), $this->linkedinManager->getAccessToken(), $linkedin_profile->getImageurl(), json_encode($data));
  }

}
