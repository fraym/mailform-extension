<?php
/**
 * @link      http://fraym.org
 * @author    Dominik Weber <info@fraym.org>
 * @copyright Dominik Weber <info@fraym.org>
 * @license   http://www.opensource.org/licenses/gpl-license.php GNU General Public License, version 2 or later (see the LICENSE file)
 */
namespace Fraym\Extension\Mailform;

use Fraym\Annotation\Registry;

/**
 * @package Fraym\Extension\Mailform
 * @Registry(
 * name="Mailform",
 * repositoryKey="fraym/mailform-extension",
 * entity={
 *      "\Fraym\Block\Entity\Extension"={
 *          {
 *           "name"="Mailform",
 *           "description"="Create a mail formular.",
 *           "class"="\Fraym\Extension\Mailform\Mailform",
 *           "execMethod"="execBlock",
 *           "configMethod"="getBlockConfig",
 *           "saveMethod"="saveBlockConfig"
 *           }
 *      }
 * }
 * )
 * @Injectable(lazy=true)
 */
class Mailform
{
    /**
     * @Inject
     * @var \Fraym\Extension\Mailform\MailformController
     */
    protected $mailformController;

    /**
     * @Inject
     * @var \Fraym\Request\Request
     */
    public $request;

    /**
     * @Inject
     * @var \Fraym\Validation\Validation
     */
    public $validation;

    /**
     * @Inject
     * @var \Fraym\Translation\Translation
     */
    public $translation;

    /**
     * @Inject
     * @var \Fraym\Mail\Mail
     */
    public $mail;

    /**
     * @Inject
     * @var \Fraym\Block\BlockParser
     */
    protected $blockParser;

    /**
     * @Inject
     * @var \Fraym\Database\Database
     */
    protected $db;

    /**
     * @Inject
     * @var \Fraym\Template\Template
     */
    protected $template;

    /**
     * @Inject
     * @var \Fraym\Locale\Locale
     */
    protected $locale;

    /**
     * @param $xml
     * @return mixed
     */
    public function execBlock($xml)
    {
        $errors = [];
        $submit = false;
        $values = $this->request->getGPAsArray();
        $requiredFields = $this->getRequiredFields($xml);

        $formHash = $this->createFormHash($requiredFields);

        if ($this->request->post('mailform')) {
            $required = $values['required'];
            $fields = $values['field'];

            if ($this->isFormValid($formHash, $this->getValidationRules($required))) {
                $submit = true;
                $this->validation->setData($fields);
                $this->validation->addRule('email', 'email');
                $errorMessages = [];

                foreach ($requiredFields as $field => $val) {
                    $this->validation->addRule($field, $val['rule'], $val['param']);
                    $errorMessages = array_merge($errorMessages, [
                        $field => [$field => $this->translation->getTranslation('Please fill out the field')],
                    ]);
                }

                $this->validation->setErrorMessages($errorMessages);
                $check = $this->validation->check();
                if ($check === true) {
                    $config = json_decode($xml->mailformOptions, true);

                    $receiver = $config[$this->locale->getLocale()->id]['email'];
                    $subject = $config[$this->locale->getLocale()->id]['subject'];

                    $msg = $this->mail->getMessageInstance();

                    $msg->setFrom([$fields['email']]);
                    $msg->setSubject($subject);
                    $msg->setTo(explode(',', $receiver));

                    $this->template->assign('fields', $fields, false);
                    $msg->setBody($this->template->fetch('Extension/Mailform/Mail'), 'text/html');
                    $this->mail->send();
                } else {
                    $errors = $check;
                }
            }
        }
        $this->mailformController->renderHtml($submit, $values, $errors);
    }

    /**
     * @param $formHash
     * @param $requiredSubmitedFields
     * @return bool
     */
    protected function isFormValid($formHash, $requiredSubmitedFields)
    {
        $hash = $this->createFormHash($requiredSubmitedFields);
        return $formHash === $hash;
    }

    /**
     * @param $fields
     * @return mixed
     */
    protected function getValidationRules($fields)
    {
        foreach ($fields as &$field) {
            if (preg_match("/([^\\(]+)\\((.*)\\)/i", $field, $match)) {
                $field = [
                    'rule' => $match[1],
                    'param' => $match[2],
                ];
            } else {
                $field = [
                    'rule' => $field,
                    'param' => null,
                ];
            }
        }
        return $fields;
    }

    /**
     * @param $fields
     * @return string
     */
    protected function createFormHash($fields)
    {
        return md5(serialize($fields));
    }

    /**
     * @param $xml
     * @return array
     */
    protected function getRequiredFields($xml)
    {
        $content = $this->getTemplateContent($xml);
        preg_match_all('@(<input.*name=\"required\\[(.*)\\]\".*/>)@im', $content, $matches);
        $fields = [];
        foreach ($matches[2] as $key => $match) {
            $htmlTag = $matches[0][$key];
            preg_match_all("/value=\"([^\"]*)\"/i", $htmlTag, $validations);
            foreach ($validations[1] as $vk => $validation) {
                if (!isset($fields[$match])) {
                    $fields[$match] = [];
                }

                $fields[$match] = $validations[1][$vk];
            }
        }
        return $this->getValidationRules($fields);
    }

    /**
     * @param $xml
     * @return null|string
     */
    protected function getTemplateContent($xml)
    {
        $content = $this->blockParser->getBlockTemplateString($xml);
        if ($content === null) {
            $template = $this->template->setView(self::class)->getTemplateFilePath('Block');
            $content = file_get_contents($template);
        }
        return $content;
    }

    /**
     * @param null $blockId
     */
    public function getBlockConfig($blockId = null)
    {
        $configXml = null;
        $config = [];
        if ($blockId) {
            $block = $this->db->getRepository('\Fraym\Block\Entity\Block')->findOneById($blockId);
            $configXml = $this->blockParser->getXmlObjectFromString($this->blockParser->wrapBlockConfig($block));
            $config = json_decode($configXml->mailformOptions, true);
        }

        $this->mailformController->getBlockConfig($config);
    }

    /**
     * @param $blockId
     * @param \Fraym\Block\BlockXml $blockXML
     * @return \Fraym\Block\BlockXml
     */
    public function saveBlockConfig($blockId, \Fraym\Block\BlockXml $blockXML)
    {
        $blockConfig = $this->request->getGPAsObject();
        $customProperties = new \Fraym\Block\BlockXmlDom();
        $element = $customProperties->createElement('mailformOptions');
        $element->appendChild($customProperties->createCDATASection(json_encode($blockConfig->config)));
        $customProperties->appendChild($element);

        $blockXML->setCustomProperty($customProperties);
        return $blockXML;
    }
}
