# ОГЭ Maths4U

Сайт для подготовки к ОГЭ по математике на базе PHP/MySQL.

## Настройка

1. Скопируйте `includes/config.example.php` в `includes/config.php`.
2. На Hostinger вручную заполните настоящий пароль базы данных в `DB_PASS`.
3. Импортируйте `database/oge_full_structure.sql` в базу `u770916388_oge_user`.
4. Зарегистрируйте первого пользователя на сайте.
5. В phpMyAdmin вручную повысьте его роль до `admin`:

```sql
UPDATE oge_users SET role = 'admin' WHERE email = 'your@email.example';
```

## Git

`includes/config.php` не должен попадать в git. В репозитории хранится только `includes/config.example.php`.
