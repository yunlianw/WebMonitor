<?php
/**
 * 通知接口 - 所有通知渠道必须实现此接口
 */
interface NotificationInterface {
    public function send(array $data): bool;
    public function test(): array;
    public function getName(): string;
    public function isEnabled(): bool;
}
