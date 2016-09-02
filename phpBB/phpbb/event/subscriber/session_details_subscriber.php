<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\event\subscriber;

use phpbb\db\driver\driver_interface;
use phpbb\security\user\user;
use phpbb\security\voter\re_authenticated_voter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;

/**
 * Updates the phpbb_sessions table.
 *
 * Called just after the security because it needs the user id.
 *
 * Loads and updates the sessions details from the database,
 * creates it if necessary (happens only when a new session is created, always for an anonymous).
 * Sets the data in the user class (BC) and the session (new dedicated session bag, similar to metadata) (new way)
 *
 * /!\ TODO: handle session_id renaming: we will need to store the old session id before session_start is called
 */
class session_details_subscriber implements EventSubscriberInterface
{
	/** @var TokenStorageInterface */
	private $token_storage;

	/** @var driver_interface */
	private $db;

	/** @var AccessDecisionManager */
	private $accessManager;

	public function __construct(driver_interface $db, TokenStorageInterface $token_storage, AccessDecisionManager $accessManager)
	{
		$this->token_storage = $token_storage;
		$this->db = $db;
		$this->accessManager = $accessManager;
	}

	/**
	 * {@inheritdoc}
	 */
	public function on_kernel_request(GetResponseEvent $event)
	{
		// Update sessions table
		$token = $this->token_storage->getToken();

		/** @var user $user */
		$user = $token->getUser();
		$request = $event->getRequest();
		$headers = $request->headers;
		$session = $request->getSession();

		$data = []; // SELECT s.* FROM sessions_table WHERE session_id = ? AND session_user_id = (already known)

		$session_data = array(
			'session_user_id'		=> $user->get_id(),
			'session_start'			=> time(),
			'session_last_visit'	=> (int) $this->data['session_last_visit'],
			'session_time'			=> time(),
			'session_browser'		=> trim(substr($headers->get('User-Agent'), 0, 149)),
			'session_forwarded_for'	=> $headers->get('X-Forwarded-For'),
			'session_ip'			=> $request->getClientIp(),

			// @deprecated
			'session_autologin'		=> $session->get('is_rememberme_allowed', false) ? 1 : 0,

			// @deprecated
			'session_admin'			=> ($this->accessManager->decide($token, [re_authenticated_voter::IS_REAUTHENTICATED])) ? 1 : 0,

			// (Set by the login form)
			'session_viewonline'	=> $session->get('is_viewonline_allowed', false) ? 1 : 0,
		);
	}

	public static function getSubscribedEvents()
	{
		return [
			KernelEvents::REQUEST => ['onKernelRequest', 39],
		];
	}
}
