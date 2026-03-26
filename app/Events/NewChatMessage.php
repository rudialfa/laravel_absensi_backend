<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChatMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly ChatMessage $message)
    {
        $this->message->load([
            'sender:id,name,image_url',
            'attachments',
            'replyTo.sender:id,name',
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->message->conversation_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.message';
    }

    public function broadcastWith(): array
    {
        $msg = $this->message;

        return [
            'id'              => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'sender'          => $msg->sender ? [
                'id'        => $msg->sender->id,
                'name'      => $msg->sender->name,
                'image_url' => $msg->sender->image_url,
            ] : null,
            'is_mine'         => false,
            'type'            => $msg->type,
            'body'            => $msg->body,
            'is_deleted'      => false,
            'is_edited'       => false,
            'edited_at'       => null,
            'attachments'     => $msg->attachments->map(fn($a) => [
                'id'               => $a->id,
                'type'             => $a->type,
                'file_url'         => $a->file_url,
                'thumbnail_url'    => $a->thumbnail_url,
                'original_name'    => $a->original_name,
                'file_size'        => $a->file_size,
                'file_size_label'  => $a->file_size_formatted,
                'mime_type'        => $a->mime_type,
                'duration_seconds' => $a->duration_seconds,
            ])->values()->toArray(),
            'reply_to'        => $msg->replyTo ? [
                'id'     => $msg->replyTo->id,
                'body'   => $msg->replyTo->body,
                'sender' => $msg->replyTo->sender?->name,
            ] : null,
            'is_read'    => false,
            'read_at'    => null,
            'created_at' => $msg->created_at->toISOString(),
        ];
    }
}
