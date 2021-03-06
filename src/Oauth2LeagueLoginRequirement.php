<?php
declare(strict_types=1);

namespace Plaisio\Login;

use League\OAuth2\Client\Provider\AbstractProvider;
use Plaisio\C;
use Plaisio\Exception\InvalidUrlException;
use Plaisio\PlaisioInterface;
use Plaisio\PlaisioObject;
use Plaisio\Response\SeeOtherResponse;

/**
 * Login Requirement: Validation against an OAuth2 server.
 */
class Oauth2LeagueLoginRequirement extends PlaisioObject implements LoginRequirement
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The OAuth2 authorization code.
   *
   * @var string|null
   */
  private $code;

  /**
   * The error message from the OAuth2 provider (when authorization has been denied).
   *
   * @var string|null
   */
  private $error;

  /**
   * The options for AbstractProvider::getAuthorizationUrl().
   *
   * @var array
   */
  private $options;

  /**
   * The OAuth provider.
   *
   * @var AbstractProvider
   */
  private $provider;

  /**
   * The give state from the OAuth2 provider.
   *
   * @var string|null
   */
  private $state;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param PlaisioInterface $object   The parent PhpPlaisio object.
   * @param AbstractProvider $provider The OAuth provider.
   * @param array            $options  The options for AbstractProvider::getAuthorizationUrl().
   *
   * @since 1.0.0
   * @api
   */
  public function __construct(PlaisioInterface $object, AbstractProvider $provider, array $options = [])
  {
    parent::__construct($object);

    $this->provider = $provider;
    $this->options  = $options;

    $this->code  = $this->nub->cgi->getOptString('code');
    $this->error = $this->nub->cgi->getOptString('error');
    $this->state = $this->nub->cgi->getOptString('state');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Validates against the OAuth2 server.
   *
   * @param array $data Non elements are required. When login is granted will be enhanced with key 'oauth2code': the
   *                    authorization code provided by the OAuth2 server.
   *
   * @return int|null
   *
   * @since 1.0.0
   * @api
   */
  public function validate(array &$data): ?int
  {
    switch (true)
    {
      case ($this->code===null && $this->error===null):
        // We don't have an authorization code yet. Fetch the authorization URL from the provider.
        $authorizationUrl = $this->provider->getAuthorizationUrl($this->options);

        // Save the state to mitigate CSRF attacks.
        $_SESSION['oauth2state'] = $this->provider->getState();

        // Redirect the user agent to the authorization URL.
        $response = new SeeOtherResponse($authorizationUrl, false);
        $response->send();

        // This is a preparation step (not a validation).
        $lgrId = null;
        break;

      case ($this->state===null || !isset($_SESSION['oauth2state']) || ($this->state!==$_SESSION['oauth2state'])):
        // The state does not match with the saved state (possible CSRF attack).
        unset($_SESSION['oauth2state']);

        throw new InvalidUrlException('Invalid state');

      case ($this->error!==null):
        // An error occurred at the OAuth2 server or the authorization request was denied.
        $lgrId = C::LGR_ID_OAUTH2_DENIED;
        break;

      default:
        // Preserve the authorization code for usage by other login requirements of by post requirement.
        $_SESSION['oauth2code'] = $this->code;
        $data['oauth2code']     = $this->code;

        // Login is granted.
        $lgrId = C::LGR_ID_GRANTED;
    }

    return $lgrId;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
