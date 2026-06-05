<?php

declare(strict_types=1);

namespace Rogga\DynamicWorkflows\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WorkflowMail extends Mailable
{
    public function __construct(
        protected array $config,
        protected Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->config['email_subject'] ?? 'Workflow Notification',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'dynamic-workflows::mail.workflow',
            with: [
                'body'  => $this->config['email_body'] ?? '',
                'model' => $this->model,
            ],
        );
    }
}
