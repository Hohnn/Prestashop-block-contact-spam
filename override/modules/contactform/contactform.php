<?php

class ContactformOverride extends Contactform
{
	public function sendMessage()
    {
        /* dqsdqsdqsdqds */
        $extension = ['.txt', '.rtf', '.doc', '.docx', '.pdf', '.zip', '.png', '.jpeg', '.gif', '.jpg'];
        $file_attachment = Tools::fileAttachment('fileUpload');
        $message = trim(Tools::getValue('message'));
        $url = Tools::getValue('url');
        $clientToken = Tools::getValue('token');
        $serverToken = $this->context->cookie->contactFormToken;
        $clientTokenTTL = $this->context->cookie->contactFormTokenTTL;

        if (!($from = trim(Tools::getValue('from'))) || !Validate::isEmail($from)) {
            $this->context->controller->errors[] = $this->trans(
                'Invalid email address.',
                [],
                'Shop.Notifications.Error'
            );
        } elseif (empty($message)) {
            $this->context->controller->errors[] = $this->trans(
                'The message cannot be blank.',
                [],
                'Shop.Notifications.Error'
            );
        } elseif (!Validate::isCleanHtml($message)) {
            $this->context->controller->errors[] = $this->trans(
                'Invalid message',
                [],
                'Shop.Notifications.Error'
            );
        } elseif (!($id_contact = (int)Tools::getValue('id_contact')) ||
                  !(Validate::isLoadedObject($contact = new Contact($id_contact, $this->context->language->id)))
        ) {
            $this->context->controller->errors[] = $this->trans(
                'Please select a subject from the list provided. ',
                [],
                'Modules.Contactform.Shop'
            );
        } elseif (!empty($file_attachment['name']) && $file_attachment['error'] != 0) {
            $this->context->controller->errors[] = $this->trans(
                'An error occurred during the file-upload process.',
                [],
                'Modules.Contactform.Shop'
            );
        } elseif (!empty($file_attachment['name']) &&
                  !in_array(Tools::strtolower(Tools::substr($file_attachment['name'], -4)), $extension) &&
                  !in_array(Tools::strtolower(Tools::substr($file_attachment['name'], -5)), $extension)
        ) {
            $this->context->controller->errors[] = $this->trans(
                'Bad file extension',
                [],
                'Modules.Contactform.Shop'
            );
        } elseif ((!Tools::getValue('from')) || Tools::getValue('blockcontactspam_answer') != Configuration::get('CONTACTFORM_ANSWER')) {
            $this->context->controller->errors[] = "La question anti spam n'est pas correct";
        } elseif ($url !== ''
            || empty($serverToken)
            || $clientToken !== $serverToken
            || $clientTokenTTL < time()
        ) {
            $this->context->controller->errors[] = $this->trans(
                'An error occurred while sending the message, please try again.',
                [],
                'Modules.Contactform.Shop'
            );
            $this->createNewToken();
        } else {
            $customer = $this->context->customer;

            if (!$customer->id) {
                $customer->getByEmail($from);
            }

            /**
             * Check that the order belongs to the customer.
             */
            $id_order = (int) Tools::getValue('id_order');
            if (!empty($id_order)) {
                $order = new Order($id_order);
                $id_order = (int) $order->id_customer === (int) $customer->id ? $id_order : 0;
            }

            $id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($from, $id_order);

            if ($contact->customer_service) {
                if ((int)$id_customer_thread) {
                    $ct = new CustomerThread($id_customer_thread);
                    $ct->status = 'open';
                    $ct->id_lang = (int)$this->context->language->id;
                    $ct->id_contact = (int)$id_contact;
                    $ct->id_order = $id_order;

                    if ($id_product = (int)Tools::getValue('id_product')) {
                        $ct->id_product = $id_product;
                    }
                    $ct->update();
                } else {
                    $ct = new CustomerThread();
                    if (isset($customer->id)) {
                        $ct->id_customer = (int)$customer->id;
                    }
                    $ct->id_shop = (int)$this->context->shop->id;
                    $ct->id_order = $id_order;

                    if ($id_product = (int)Tools::getValue('id_product')) {
                        $ct->id_product = $id_product;
                    }
                    $ct->id_contact = (int)$id_contact;
                    $ct->id_lang = (int)$this->context->language->id;
                    $ct->email = $from;
                    $ct->status = 'open';
                    $ct->token = Tools::passwdGen(12);
                    $ct->add();
                }

                if ($ct->id) {
                    $lastMessage = CustomerMessage::getLastMessageForCustomerThread($ct->id);
                    $testFileUpload = (isset($file_attachment['rename']) && !empty($file_attachment['rename']));

                    // if last message is the same as new message (and no file upload), do not consider this contact
                    if ($lastMessage != $message || $testFileUpload) {
                        $cm = new CustomerMessage();
                        $cm->id_customer_thread = $ct->id;
                        $cm->message = $message;

                        if ($testFileUpload && rename($file_attachment['tmp_name'], _PS_UPLOAD_DIR_ . basename($file_attachment['rename']))) {
                            $cm->file_name = $file_attachment['rename'];
                            @chmod(_PS_UPLOAD_DIR_ . basename($file_attachment['rename']), 0664);
                        }
                        $cm->ip_address = (int)ip2long(Tools::getRemoteAddr());
                        $cm->user_agent = $_SERVER['HTTP_USER_AGENT'];

                        if (!$cm->add()) {
                            $this->context->controller->errors[] = $this->trans(
                                'An error occurred while sending the message.',
                                [],
                                'Modules.Contactform.Shop'
                            );
                        }
                    } else {
                        $mailAlreadySend = true;
                    }
                } else {
                    $this->context->controller->errors[] = $this->trans(
                        'An error occurred while sending the message.',
                        [],
                        'Modules.Contactform.Shop'
                    );
                }
            }
            $sendConfirmationEmail = Configuration::get(self::SEND_CONFIRMATION_EMAIL);
            $sendNotificationEmail = Configuration::get(self::SEND_NOTIFICATION_EMAIL);

            if (!count($this->context->controller->errors)
                && empty($mailAlreadySend)
                && ($sendConfirmationEmail || $sendNotificationEmail)
            ) {
                $var_list = [
                    '{firstname}' => '',
                    '{lastname}' => '',
                    '{order_name}' => '-',
                    '{attached_file}' => '-',
                    '{message}' => Tools::nl2br(Tools::htmlentitiesUTF8(Tools::stripslashes($message))),
                    '{email}' =>  $from,
                    '{product_name}' => '',
                ];

                if (isset($customer->id)) {
                    $var_list['{firstname}'] = $customer->firstname;
                    $var_list['{lastname}'] = $customer->lastname;
                }

                if (isset($file_attachment['name'])) {
                    $var_list['{attached_file}'] = $file_attachment['name'];
                }
                $id_product = (int)Tools::getValue('id_product');

                if ($id_order) {
                    $order = new Order((int)$id_order);
                    $var_list['{order_name}'] = $order->getUniqReference();
                    $var_list['{id_order}'] = (int)$order->id;
                }

                if ($id_product) {
                    $product = new Product((int)$id_product);

                    if (Validate::isLoadedObject($product) &&
                        isset($product->name[Context::getContext()->language->id])
                    ) {
                        $var_list['{product_name}'] = $product->name[Context::getContext()->language->id];
                    }
                }

                if ($sendNotificationEmail) {
                    if (empty($contact->email) || !Mail::Send(
                        $this->context->language->id,
                        'contact',
                        $this->trans('Message from contact form', [], 'Emails.Subject').' [no_sync]',
                        $var_list,
                        $contact->email,
                        $contact->name,
                        null,
                        null,
                        $file_attachment,
                        null,
                        _PS_MAIL_DIR_,
                        false,
                        null,
                        null,
                        $from
                    )) {
                        $this->context->controller->errors[] = $this->trans(
                            'An error occurred while sending the message.',
                            [],
                            'Modules.Contactform.Shop'
                        );
                    }
                }

                if ($sendConfirmationEmail) {
                    $var_list['{message}'] = self::MESSAGE_PLACEHOLDER_FOR_OLDER_VERSION;

                    if (!Mail::Send(
                        $this->context->language->id,
                        'contact_form',
                        ((isset($ct) && Validate::isLoadedObject($ct)) ? $this->trans(
                            'Your message has been correctly sent #ct%thread_id% #tc%thread_token%',
                            [
                                '%thread_id%' => $ct->id,
                                '%thread_token%' => $ct->token
                            ],
                            'Emails.Subject'
                        ) : $this->trans('Your message has been correctly sent', [], 'Emails.Subject')),
                        $var_list,
                        $from,
                        null,
                        null,
                        null,
                        $file_attachment,
                        null,
                        _PS_MAIL_DIR_,
                        false,
                        null,
                        null,
                        $contact->email
                    )) {
                        $this->context->controller->errors[] = $this->trans(
                            'An error occurred while sending the message.',
                            [],
                            'Modules.Contactform.Shop'
                        );
                    }
                }
            }

            if (!count($this->context->controller->errors)) {
                $this->context->controller->success[] = $this->trans(
                    'Your message has been successfully sent to our team.',
                    [],
                    'Modules.Contactform.Shop'
                );
            }
        }
    }
}