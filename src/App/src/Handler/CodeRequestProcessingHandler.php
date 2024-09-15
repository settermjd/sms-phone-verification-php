<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessagesInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Twilio\Rest\Client;

readonly final class CodeRequestProcessingHandler implements RequestHandlerInterface
{
    private InputFilter $inputFilter;

    public function __construct(private Client $client, private string $verificationSid)
    {
        $username = new Input("username");
        $username->setRequired(true);
        $username->getFilterChain()
            ->attach(new StringTrim())
            ->attach(new StripTags());
        $username->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new StringLength(['max' => 255]));

        $password = new Input("password");
        $password->setRequired(true);
        $password->getFilterChain()
            ->attach(new StringTrim())
            ->attach(new StripTags());
        $password->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new StringLength(['min' => 10]));

        $number = new Input("number");
        $number->setRequired(true);
        $number->getFilterChain()
            ->attach(new StringTrim())
            ->attach(new StripTags());
        $number->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new Regex("/^\+[1-9]\d{1,14}$/"));

        $this->inputFilter = new InputFilter();
        $this->inputFilter
            ->add($username)
            ->add($password)
            ->add($number);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ?FlashMessagesInterface $flashMessages */
        $flashMessages = $request->getAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, null);

        $this->inputFilter->setData($request->getParsedBody() ?? []);
        if ($this->inputFilter->isValid()) {
            $phoneNumber = (string) $this->inputFilter->getValue("number");
            $this->client
                ->verify
                ->v2
                ->services($this->verificationSid)
                ->verifications
                ->create($phoneNumber, "sms");
            $flashMessages?->flash("phone-number", $phoneNumber);
            return new RedirectResponse("/verify");
        }

        $flashMessages?->flash("form-error", "The form contains errors");
        $flashMessages?->flash("form-data", $this->inputFilter->getValues());

        return new RedirectResponse("/");
    }
}