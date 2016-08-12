<?php

namespace PHPCraft\Logging;

/**
 * PHPCraft subject that manages log in/out to an area
 * @author vuk <http://vuk.bg.it>
 */

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use PHPCraft\Template\TemplateInterface;
use PHPCraft\Cookie\CookieInterface;
use PHPCraft\Subject\Subject;

class Logging extends Subject
{
    
    protected $loginPage;
    protected $loggedFirstPage;
    protected $message;
    
    /**
     * Constructor.
     * @param Psr\Http\Message\RequestInterface $httpRequest HTTP request handler instance
     * @param Psr\Http\Message\ResponseInterface $httpResponse HTTP response handler instance
     * @param Psr\Http\Message\StreamInterface $httpStream HTTP stream handler instance
     * @param PHPCraft\Template\TemplateInterface $template template renderer instance
     * @param PHPCraft\Cookie\CookieInterface $cookie, instance
     * @param string $application current PHPCraft application
     * @param string $area current PHPCraft area
     * @param string $subject current PHPCraft subject
     * @param string $action current PHPCraft action
     * @param string $language current PHPCraft language code
     * @param PHPCraft\Message\Message $message instance
     * @param Aura\Auth\AuthFactory $authFactory instance
     * @param string $loginPage to redirect to in case of failed authentication
     * @param string $defaultPage first page to serve after successful login (unless another page has been previously requested)
     * @param array $routeParameters informations extracted from current request by route matching pattern
     **/
    public function __construct(
        RequestInterface &$httpRequest,
        ResponseInterface &$httpResponse,
        StreamInterface &$httpStream,
        TemplateInterface $template,
        CookieInterface $cookie,
        $application,
        $area,
        $subject,
        $action,
        $language,
        \PHPCraft\Message\Message $message,
        \Aura\Auth\AuthFactory $authFactory,
        $loginPage,
        $defaultPage,
        $routeParameters = array()
        
    ) {
        parent::__construct($httpRequest, $httpResponse, $httpStream,$template, $cookie, $application, $area, $subject, $action, $language, $routeParameters);
        $this->authFactory = $authFactory;
        $this->message = $message;
        $this->message->setCookie($cookie);
        $this->loginPage = $loginPage;
        $this->defaultPage = $defaultPage;
    }
    
/**
     * Tries to exec current action
     *
     * @throws Exception if there is no method defined to handle action
     **/
    public function execAction()
    {
        $this->templateParameters['pageTitle'] = $this->translations[$this->subject]['name'];
        $this->templateParameters['messages'] = $this->message->get('cookies');
        $this->message->clear('cookies');
        parent::execAction();
    }
    
    /**
     * displays login form
     */
    protected function execIn()
    {
        $this->addTranslations('form', sprintf('private/global/locales/%s/form.ini', $this->language));
        $this->templateParameters['translations'] = $this->translations;
        $this->renderTemplate();
    }
    
    /**
     * authenticates user
     */
    protected function execAuthenticate()
    {
        //get input
        $args = array(
            'username' => FILTER_SANITIZE_STRING,
            'password' => FILTER_SANITIZE_STRING
        );
        $input = filter_input_array(INPUT_POST, $args);
        $path = sprintf('private/%s/configurations/%s/.htpasswd', $this->application, $this->area);
        $htpasswdAdapter = $this->authFactory->newHtpasswdAdapter($path);
        $loginService = $this->authFactory->newLoginService($htpasswdAdapter);
        $auth = $this->authFactory->newInstance();
        try {
            $loginService->login($auth, array(
                'username' => $input['username'],
                'password' => $input['password']
            ));
            $error = false;
        } catch(\Aura\Auth\Exception\UsernameNotFound $e) {
            $error = true;
            $message = 'wrong_username';
        } catch(\Aura\Auth\Exception\PasswordIncorrect $e) {
            $error = true;
            $message = 'wrong_password';
        }
        if($error){
            $this->message->save('cookies','danger',$this->translations[$this->subject][$message]);
            $this->httpResponse = $this->httpResponse->withHeader('Location', $this->loginPage);
        }else{
            $loginRequestedUrl = $this->cookie->get('authenticationRequestedUrl', $this->defaultPage);
            $this->cookie->delete('authenticationRequestedUrl');
            $this->httpResponse = $this->httpResponse->withHeader('Location', $loginRequestedUrl);
        }
    }
    
    /**
     * authenticates user
     */
    protected function execOut()
    {
        $logoutService = $this->authFactory->newLogoutService();
        $auth = $this->authFactory->newInstance();
        $logoutService->logout($auth);
        $this->httpResponse = $this->httpResponse->withHeader('Location', $this->loginPage);
    }
}