<?php

namespace Tests\Feature\Mail;

use App\Mail\Contracts\LoggableEmail;
use App\Mail\KitMailable;
use App\Mail\PreviewMail;
use App\Mail\TestMail;
use App\Models\EmailLog;
use App\Support\Mail\EmailCatalog;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Every email declares its catalog type and which public property holds the
 * record it is about, so the email log can link each send to its record
 * (spec §5.3) — and a new email can never silently skip that.
 */
class LoggableEmailCoverageTest extends TestCase
{
    /** @return list<class-string> */
    private function mailables(): array
    {
        $classes = [];
        foreach (glob(app_path('Mail/*.php')) as $file) {
            $class = 'App\\Mail\\'.basename($file, '.php');
            $reflection = new ReflectionClass($class);
            // Tooling mails (mail:preview / mail:test) aren't catalog emails; the log
            // files them under their class name.
            if ($reflection->isAbstract() || $reflection->isInterface() || in_array($class, [PreviewMail::class, TestMail::class], true)) {
                continue;
            }
            $classes[] = $class;
        }

        return $classes;
    }

    public function test_every_mailable_declares_a_catalog_type_and_its_related_record(): void
    {
        $this->assertNotEmpty($this->mailables());

        foreach ($this->mailables() as $class) {
            $this->assertTrue(is_subclass_of($class, LoggableEmail::class), "{$class} must implement LoggableEmail");
            $this->assertTrue(EmailCatalog::has($class::emailType()), "{$class}: unknown type {$class::emailType()}");

            $property = $class::relatedProperty();
            if ($property !== null) {
                $this->assertTrue(property_exists($class, $property), "{$class}: no property \${$property}");
                $this->assertTrue((new ReflectionProperty($class, $property))->isPublic(), "{$class}: \${$property} must be public");
            }
        }
    }

    public function test_kit_mailables_are_loggable(): void
    {
        $this->assertTrue(is_subclass_of(KitMailable::class, LoggableEmail::class));
    }

    public function test_related_aliases_resolve_to_models(): void
    {
        foreach (EmailLog::RELATED as $alias => $class) {
            $this->assertTrue(class_exists($class), $alias);
            $this->assertSame($alias, EmailLog::aliasFor(new $class));
        }
    }
}
