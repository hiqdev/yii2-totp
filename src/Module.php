<?php
/**
 * Multi-factor authentication for Yii2 projects
 *
 * @link      https://github.com/hiqdev/yii2-mfa
 * @package   yii2-mfa
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2016-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\yii2\mfa;

use hiqdev\yii2\mfa\base\MfaIdentityInterface;
use hiqdev\yii2\mfa\base\Totp;
use hiqdev\yii2\mfa\exceptions\IpNotAllowedException;
use hiqdev\yii2\mfa\exceptions\TotpVerificationFailedException;
use Yii;
use yii\di\Instance;
use yii\validators\IpValidator;

/**
 * Multi-factor authentication module.
 *
 * @author Andrii Vasyliev <sol@hiqdev.com>
 */
class Module extends \yii\base\Module
{
    public $paramPrefix = 'MFA-';

    protected $_totp;

    public function setTotp($value)
    {
        $this->_totp = $value;
    }

    public function getTotp()
    {
        if (!is_object($this->_totp)) {
            $this->_totp = Instance::ensure($this->_totp, Totp::class);
            $this->_totp->module = $this;
        }

        return $this->_totp;
    }

    public function sessionSet($name, $value)
    {
        Yii::$app->session->set($this->paramPrefix . $name, $value);
    }

    public function sessionGet($name)
    {
        return Yii::$app->session->get($this->paramPrefix . $name);
    }

    public function sessionRemove($name)
    {
        return Yii::$app->session->remove($this->paramPrefix . $name);
    }

    public function setHalfUser(MfaIdentityInterface $value)
    {
        $this->sessionSet('half-user-id', $value->getId());
        $this->sessionSet('totp-tmp-secret', $value->getTotpSecret());
    }

    public function getHalfUser(): ?MfaIdentityInterface
    {
        $id = $this->sessionGet('half-user-id');
        $class = Yii::$app->user->identityClass;

        return $class::findIdentity($id);
    }

    public function removeHalfUser()
    {
        $this->sessionRemove('half-user-id');
        $this->sessionRemove('totp-tmp-secret');
    }

    public function validateIps(MfaIdentityInterface $identity)
    {
        if (empty($identity->getAllowedIps())) {
            return;
        }
        $ips = array_filter($identity->getAllowedIps());
        $validator = new IpValidator([
            'ipv6' => false,
            'ranges' => $ips,
        ]);
        if ($validator->validate(Yii::$app->request->getUserIP())) {
            return;
        }

        throw new IpNotAllowedException();
    }

    public function validateTotp(MfaIdentityInterface $identity)
    {
        if (empty($identity->getTotpSecret())) {
            return;
        }
        if ($this->getTotp()->getIsVerified()) {
            return;
        }

        throw new TotpVerificationFailedException();
    }
}
