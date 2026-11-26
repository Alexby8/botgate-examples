<?php

declare(strict_types=1);
return [
    'welcome' => "Hello! This is a booking demo bot.\nWhat should we call you? Enter a name between 2 and 60 characters.",
    'ask_name' => 'Enter a name between 2 and 60 characters.',
    'saved' => 'Thank you, :name. Your name has been saved. This is a demo: no real appointment has been booked.',
    'choose_language' => 'Choose your language. We will remember it for future messages.',
    'language_saved' => 'Language changed to English.',
    'language_button' => 'Change language',
    'help_button' => 'Help',
    'help' => '/start — start the conversation; /language — choose a language; /help — help. Notifications use your saved language.',
    'bad_name' => 'Your name must contain between 2 and 60 characters. Please try again.',
    'unknown' => 'Command not recognized. Use /start, /language or /help.',
    'stale' => 'This button is no longer supported. Open /language or /start.',
    'notice' => 'Demo notification for request :reference: the status has changed. Send /help if you need assistance.',
];
