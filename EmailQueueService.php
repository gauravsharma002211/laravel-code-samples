<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;

class EmailQueueService
{
    public function sendEmail($emailQueue)
    {
        $attachments = [];
        if ($emailQueue->attachments) {
            $files = json_decode($emailQueue->attachments, true);
            foreach ($files as $file) {
                if (File::isFile(public_path($file['file_path']))) {
                    $attachments[] = \Swift_Attachment::fromPath(public_path($file['file_path']));
                }
            }
            if (sizeof($attachments)) {
                Mail::send([], [], function ($message) use ($emailQueue, $attachments) {
                    $message = $message->to($emailQueue->email)
                        ->subject($emailQueue->subject)
                        ->setBody($emailQueue->content, 'text/html');
        
                    if ($attachments) {
                        foreach ($attachments  as $file) {
                            $message->attach($file);
                        }
                    }
                });
            }
        } else {
            Mail::send([], [], function ($message) use ($emailQueue, $attachments) {
                $message = $message->to($emailQueue->email)
                    ->subject($emailQueue->subject)
                    ->setBody($emailQueue->content, 'text/html');
            });
        }
    }
}
