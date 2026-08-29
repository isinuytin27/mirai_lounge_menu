<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Начальная схема Mirai Lounge. Строковые ID из старых JSON сохранены как первичные
 * ключи (varchar) — это сохраняет существующие ссылки (order_items.product_id,
 * vip_guests.event_id и т.д.) и делает импорт идемпотентным по id.
 */
final class CreateCoreSchema extends AbstractMigration
{
    public function change(): void
    {
        $ts = ['timezone' => true];

        // --- Меню: категории ---
        $this->table('menu_categories', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('title', 'text')
            // Линия выдачи (hookah|bar|kitchen) — раньше хардкод в mirai_menu_line.php,
            // теперь данные: редактируемо, продукт наследует линию своей категории.
            ->addColumn('line', 'string', ['limit' => 16, 'default' => 'kitchen'])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->create();

        // --- Меню: продукты ---
        $this->table('menu_products', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('category_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('name', 'text')
            ->addColumn('price', 'integer', ['default' => 0])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('description_short', 'text', ['null' => true])
            ->addColumn('image', 'text', ['null' => true])
            ->addColumn('weight', 'text', ['null' => true])
            ->addColumn('visible', 'boolean', ['default' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('category_id')
            ->addForeignKey('category_id', 'menu_categories', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        // --- Галерея ---
        $this->table('gallery_items', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('image', 'text')
            ->addColumn('caption', 'text', ['null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->create();

        // --- Столы (первокласс, а не элемент галереи) ---
        $this->table('tables', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('caption', 'text', ['null' => true])
            ->addColumn('capacity', 'integer', ['null' => true])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

        // --- Заказы ---
        $this->table('orders', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('table_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('table_caption', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'open'])
            ->addColumn('created_at', 'timestamp', $ts + ['null' => true])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->addColumn('closed_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('status')
            ->addIndex('table_id')
            ->addForeignKey('table_id', 'tables', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        // --- Позиции заказа ---
        $this->table('order_items')
            ->addColumn('order_id', 'string', ['limit' => 128])
            ->addColumn('product_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('name', 'text')
            ->addColumn('qty', 'integer', ['default' => 1])
            ->addColumn('price', 'integer', ['default' => 0])
            ->addColumn('line', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('category_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('added_at', 'timestamp', $ts + ['null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addIndex('order_id')
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // --- VIP: события ---
        $this->table('vip_events', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('slug', 'string', ['limit' => 128])
            ->addColumn('organization', 'text', ['null' => true])
            ->addColumn('event_date', 'date', ['null' => true])
            ->addColumn('bar_free_limit', 'integer', ['default' => 0])
            ->addColumn('bar_line', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('active', 'boolean', ['default' => false])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('slug', ['unique' => true])
            ->create();

        // --- VIP: гости ---
        $this->table('vip_guests', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('event_id', 'string', ['limit' => 128])
            ->addColumn('token', 'string', ['limit' => 128])
            ->addColumn('first_name', 'text', ['null' => true])
            ->addColumn('last_name', 'text', ['null' => true])
            ->addColumn('organization', 'text', ['null' => true])
            ->addColumn('free_used', 'integer', ['default' => 0])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('token', ['unique' => true])
            ->addIndex('event_id')
            ->addForeignKey('event_id', 'vip_events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // --- VIP: списания (старое поле guest.lines[]) ---
        $this->table('vip_consumptions')
            ->addColumn('guest_id', 'string', ['limit' => 128])
            ->addColumn('product_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('line', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('paid_by_guest', 'boolean', ['default' => false])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('guest_id')
            ->addForeignKey('guest_id', 'vip_guests', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // --- Турниры (пока один, но схема допускает несколько) ---
        $this->table('tournaments')
            ->addColumn('title', 'text', ['null' => true])
            ->addColumn('max_slots', 'integer', ['default' => 0])
            ->addColumn('format', 'text', ['null' => true])
            ->addColumn('roster', 'text', ['null' => true])
            ->addColumn('deadline', 'text', ['null' => true])
            ->addColumn('fee', 'text', ['null' => true])
            ->addColumn('registration_open', 'boolean', ['default' => false])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->create();

        // --- Заявки на турнир ---
        $this->table('tournament_applications', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('tournament_id', 'integer')
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'new'])
            ->addColumn('team_name', 'text', ['null' => true])
            ->addColumn('rating', 'text', ['null' => true])
            ->addColumn('experience', 'text', ['null' => true])
            ->addColumn('captain_name', 'text', ['null' => true])
            ->addColumn('captain_steam', 'text', ['null' => true])
            ->addColumn('captain_telegram', 'text', ['null' => true])
            ->addColumn('captain_phone', 'text', ['null' => true])
            ->addColumn('comment', 'text', ['null' => true])
            // players:[{nick,steam}] и sources:[] сохраняем как jsonb — без потери данных
            ->addColumn('players', 'jsonb', ['null' => true])
            ->addColumn('sources', 'jsonb', ['null' => true])
            ->addColumn('created_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('tournament_id')
            ->addIndex('status')
            ->addForeignKey('tournament_id', 'tournaments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // --- Тикеты (внутренние заявки персонала) ---
        $this->table('tickets', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('title', 'text')
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('category', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('priority', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'open'])
            ->addColumn('created_by', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', $ts + ['null' => true])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('status')
            ->create();

        // --- Админ-пользователи (bcrypt-хэши) ---
        $this->table('admin_users', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 128])
            ->addColumn('login', 'string', ['limit' => 128])
            ->addColumn('password_hash', 'text')
            ->addColumn('first_name', 'text', ['null' => true])
            ->addColumn('last_name', 'text', ['null' => true])
            ->addColumn('role', 'string', ['limit' => 32, 'default' => 'staff'])
            ->addColumn('created_at', 'timestamp', $ts + ['null' => true])
            ->addColumn('updated_at', 'timestamp', $ts + ['null' => true])
            ->addIndex('login', ['unique' => true])
            ->create();

        // --- Web Push подписки ---
        $this->table('push_subscriptions')
            ->addColumn('endpoint', 'text')
            ->addColumn('p256dh', 'text', ['null' => true])
            ->addColumn('auth', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', $ts + ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('endpoint', ['unique' => true])
            ->create();
    }
}
