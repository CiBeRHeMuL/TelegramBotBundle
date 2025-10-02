# AndrewGos Telegram Bot Bundle

[![Latest Version](https://img.shields.io/github/v/release/CiBeRHeMuL/TelegramBotBundle)](https://github.com/CiBeRHeMuL/TelegramBotBundle/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/CiBeRHeMuL/TelegramBotBundle/ci.yml?branch=master)](https://github.com/CiBeRHeMuL/TelegramBotBundle/actions)
[![PHP Version Require](http://poser.pugx.org/andrew-gos/telegram-bot-bundle/require/php)](https://packagist.org/packages/andrew-gos/telegram-bot-bundle)

An advanced, strictly typed, and feature-rich Symfony bundle for creating Telegram bots, built on top of the powerful `andrew-gos/telegram-bot` library. This bundle fully leverages the Symfony ecosystem to provide a seamless and highly configurable development experience.

---

### ✨ Key Features

*   **🐘 Multi-Bot Support:** Effortlessly configure and manage multiple bots within a single Symfony application.
*   **⚙️ Flexible Configuration:** Powerful and intuitive YAML configuration for bots, update handlers, plugins, and middlewares.
*   **🕹️ Console Commands:** A rich set of `bin/console` commands to manage webhooks, listen for updates (long-polling), and inspect bot status.
*   **🧩 Extensible Architecture:** Easily create your own update handlers, checkers, middlewares, and plugins as standard Symfony services.
*   **🛡️ Strictly Typed:** Benefit from a fully typed API that provides excellent autocompletion and reduces runtime errors.
*   **🔗 Symfony Integration:** Built from the ground up to work with Symfony's Dependency Injection, Event Dispatcher, and other core components.

---

### 🚀 Installation

Install the bundle into your Symfony project using Composer.

```bash
composer require andrew-gos/telegram-bot-bundle
```

The bundle will be automatically enabled by Symfony Flex.

---

### ⚙️ Configuration

This is where the magic happens. The bundle's configuration is powerful and allows you to define every aspect of your bot's behavior.

#### 1. Create the Configuration File

When you install the bundle, our **Symfony Flex recipe** will automatically generate an initial, well-commented configuration file for you at the following location:

```
config/packages/andrew_gos_telegram_bot.yaml
```

This file will serve as a great starting point for your own configuration.

If you have chosen not to use Flex recipes, or if the file was not created for any reason, you can create it manually. Simply run the following command from your project's root directory:

```bash
touch config/packages/andrew_gos_telegram_bot.yaml
```

---
### 🧠 **Why this is better:**

*   **Sets correct expectations:** It immediately informs the 99% of users with Flex enabled that the work is already done for them.
*   **Provides a clear fallback:** It gives a simple, direct instruction for those who need to create the file manually.
*   **Enhances professionalism:** It shows that the bundle is modern and fully integrated with the Symfony ecosystem's best practices.

#### 2. Minimal Configuration (Quick Start)

Here is the minimum configuration required to get a single bot running. This bot will use long-polling.

```yaml
# config/packages/andrew_gos_telegram_bot.yaml
andrew_gos_telegram_bot:
    bots:
        # A unique name for your bot
        my_first_bot:
            factory:
                # 'getUpdates' is a shortcut for the long-polling factory
                method: 'getUpdates'
                arguments:
                    # Best practice: use environment variables for your token!
                    $token: '%env(string:TELEGRAM_BOT_TOKEN)%'
            
            # Define at least one handler to process messages
            handlers:
                - handler: App\Telegram\Handler\DefaultMessageHandler
```

Now, add your token to the `.env` file:

```dotenv
# .env
TELEGRAM_BOT_TOKEN="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
```

#### 3. Full Configuration Guide

The bundle's configuration is split into three main sections for each bot: `factory`, `plugins`, and `handlers`.

##### **The `factory` section (Required)**

This defines how the `Telegram` service instance is created. You can use one of two shortcuts:
*   `'default'`: The standard factory, best for **webhooks**.
*   `'getUpdates'`: The factory for **long-polling** via the `listen` command.

##### **The `plugins` section (Optional)**

Plugins are services that run on **every incoming update** before the main handlers. They are perfect for logging, analytics, or initializing session data.

```yaml
plugins:
    - class: App\Telegram\Plugin\RequestLoggerPlugin
      arguments:
          $logger: '@monolog.logger.telegram'
```

##### **The `handlers` section (Required for bot logic)**

This is the core of your bot. It's a list of handler groups, executed based on `priority`. A handler group has four parts:

*   `checker` (Required): A service that decides if this handler should run for the current update.
*   `handler` (Required): The service that contains the actual business logic.
*   `middlewares` (Optional): A chain of services that run after the `checker` and before the `handler`.
*   `priority` (Optional, default `0`): A number to control the execution order. **Higher priority runs first**.

> **Execution Flow**: For any update, the bundle checks each handler group from highest to lowest priority. The **first group whose `checker` returns true** gets its `middlewares` and `handler` executed, and the process stops.

##### **Complete Example**

This example shows a complex setup with two bots, demonstrating most features.

```yaml
# config/packages/andrew_gos_telegram_bot.yaml
andrew_gos_telegram_bot:
    bots:
        # A powerful main bot configured for webhooks.
        my_main_bot:
            factory:
                method: 'default'
                arguments:
                    $token: '%env(string:TELEGRAM_MAIN_BOT_TOKEN)%'

            plugins:
                - class: App\Telegram\Plugin\RequestLoggerPlugin
                  arguments:
                      $logger: '@monolog.logger.telegram'

            handlers:
                # Handles the '/start' command with high priority.
                -   checker:
                        class: App\Telegram\Checker\CommandChecker
                        arguments: { $command: '/start' }
                    handler: App\Telegram\Handler\StartCommandHandler
                    middlewares:
                        - App\Telegram\Middleware\UserAuthenticationMiddleware
                    priority: 100

                # A fallback handler for any other text message.
                # If 'checker' is omitted, it defaults to AnyChecker (always runs).
                -   handler: App\Telegram\Handler\DefaultMessageHandler
                    priority: -100

        # A simple second bot for sending notifications.
        my_notification_bot:
            factory:
                method: 'getUpdates'
                arguments:
                    $token: '%env(string:TELEGRAM_NOTIFY_BOT_TOKEN)%'
```

---

### 🕹️ Usage

#### Console Commands

The bundle provides a set of commands to manage your bots. You must always specify the bot's name from your configuration file.

*   **Listen for Updates (Long-Polling)**
    ```bash
    php bin/console andrew-gos:telegram-bot:listen my_main_bot
    ```

*   **Set a Webhook**
    ```bash
    # The URL must be HTTPS
    php bin/console andrew-gos:telegram-bot:set-webhook my_main_bot https://my-domain.com/telegram/webhook
    ```

*   **Get Webhook Info**
    ```bash
    php bin/console andrew-gos:telegram-bot:webhook-info my_main_bot
    ```

*   **Delete a Webhook**
    ```bash
    php bin/console andrew-gos:telegram-bot:delete-webhook my_main_bot
    ```

*   **Get Bot Info** (useful for checking if the token is correct)
    ```bash
    php bin/console andrew-gos:telegram-bot:bot-info my_main_bot
    ```

#### Creating Your Services

Your bot's logic lives in services. Here are skeletons for the main types.

*   **Checker** (`App\Telegram\Checker\CommandChecker.php`)
    A checker must implement `CheckerInterface`.

    ```php
    <?php
    
    namespace App\Telegram\Checker;
    
    use AndrewGos\TelegramBot\Kernel\Checker\CheckerInterface;
    use AndrewGos\TelegramBot\Entity\Update;
    
    readonly class CommandChecker implements CheckerInterface
    {
        public function __construct(
            private string $command,
        ) {}
    
        public function check(Update $update): bool
        {
            $message = $update->getMessage();
            return $message && $message->getText() === $this->command;
        }
    }
    ```

*   **Handler** (`App\Telegram\Handler\StartCommandHandler.php`)
    A handler must implement `HandlerInterface`.

    ```php
    <?php
    
    namespace App\Telegram\Handler;
    
    use AndrewGos\TelegramBot\Kernel\Handler\HandlerInterface;
    use AndrewGos\TelegramBot\Entity\Update;
    use AndrewGos\TelegramBot\Telegram;
    use AndrewGos\TelegramBot\Request\SendMessageRequest;
    
    class StartCommandHandler implements HandlerInterface
    {
        public function handle(Update $update, Telegram $telegram): void
        {
            $chatId = $update->getMessage()->getChat()->getId();
            $telegram->getApi()->sendMessage(
                new SendMessageRequest($chatId, 'Hello! Welcome to the bot.')
            );
        }
    }
    ```

---

### 🤝 Contributing

Contributions are welcome! If you've found a bug or have an idea for a new feature, please open an issue on GitHub. If you'd like to contribute code, please open a pull request.

### 📜 License

This bundle is released under the **MIT license**. See the bundled [LICENSE](LICENSE) file for details.
