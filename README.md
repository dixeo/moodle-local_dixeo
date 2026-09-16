# Dixeo AI — Core Plugin for Moodle

Foundation plugin that powers the Dixeo AI ecosystem for Moodle 4.5+. This plugin provides the shared services, API communication, and intelligence layer used by the other Dixeo plugins.

**This plugin does not provide a user interface on its own.** It is required by:

- **[Dixeo Editor](../../../moodle-local_dixeo_editor)** — AI-powered content editing for pages and labels
- **[Dixeo Module Generator](../../../moodle-block_dixeo_modulegen)** — Generate new course activities with AI
- **[Dixeo Tutor](../../../moodle-block_dixeo_tutor)** — AI tutor chatbot for students ("Ask Ed")
- **[Dixeo Course Designer](../../../moodle-block_dixeo_designer)** — Use AI to design a complete course from source documents with a customisable pedagogicial template

## What it does

- **Module generation** — Create pages, labels, quizzes, glossaries, and slideshows from natural language instructions
- **Content editing** — Make targeted AI edits to existing module content
- **Course generation** — Generate full course structures (sections + modules) from a description, optionally grounded in uploaded documents (PDF, DOCX, TXT, PPTX)
- **Course templates** — Reusable pedagogical templates that constrain how courses are structured (e.g., ABC Learning Design, Bloom's Taxonomy)
- **AI tutoring** — Context-aware conversational assistant that understands the course content
- **File sync** — Automatically indexes course documents so AI can reference them during generation and tutoring
- **Credit management** — Track usage, balance, and transaction history

## File Synchronisation

When at least one of the Dixeo Generator or Tutor blocks are deployed to a course, course documents are automatically synchronised with the Dixeo platform.
Synchronization to Dixeo allows generated activities to remain grounded in the latest course documentation.

The synchronization "pill" indicator displays:

| Colour | Meaning |
|---------|----------|
| Green | All files synchronized |
| Orange | Synchronization required |
| Blue | Synchronization in progress |
| Grey | No files available |
| Red | Synchronization error |

Teachers can:
- manually trigger synchronization;
- pause synchronization;
- disable synchronization;
- review the last synchronization date.

## Requirements

- Moodle 4.5+
- PHP 8.1+
- A Dixeo API key

## Aquiring a Dixeo API key

You will receive a Dixeo API key within 48 hours of payment.

In case of delay or difficulty, please contact support@dixeo.com.

## Installation

1. Copy `dixeo` to `/local/dixeo/`
2. Visit Site Administration > Notifications
3. Configure at Site Administration > Plugins > Local plugins > Dixeo AI

## Configuration

| Setting | Description |
|---------|-------------|
| **API URL** | Dixeo API endpoint (default: `https://api.dixeo.com`) |
| **API Key** | Your Dixeo API key (required) |
| **Namespace** | Site identifier for multi-site isolation (default: `default`) |

## Capabilities

| Capability | Description | Default Roles |
|------------|-------------|---------------|
| `local/dixeo:manage` | Manage settings and view admin reports | Manager |
| `local/dixeo:generate` | Generate new modules with AI | Editing Teacher, Manager |
| `local/dixeo:edit` | Edit existing modules with AI | Editing Teacher, Manager |
| `local/dixeo:create` | Create courses using Dixeo Course Designer | Manager, Course Creator |
| `local/dixeo:talktotutor` | Interact with the AI Tutor | Manager, Editing Teacher, Non-Editing Teacher, Student |
| `local/dixeo:viewusage` | View site-wide credit usage (overview, detailed report, and export). Exposes user and course names across the whole site. Assign only to trusted roles; managers receive it by default. Users with `local/dixeo:manage` can also access the report. | Manager |

## For developers — extending the AI context

The context Dixeo builds for a course only describes what the current user may see
(`uservisible` on both sections and modules). Two hooks let other plugins extend it.

Dixeo natively reads page, label, book, url and resource. Any other module type goes
through three steps, in order, and the first one that returns text wins:

1. its search area — a module shipping a `mod_<name>\search\activity` class is read
   through it (activity level only: the intro and whatever else the area puts in the
   document). The document is built on demand, so global search does not have to be
   enabled; the site only needs a search engine selected, which Moodle does by default;
2. the `extract_module_content` hook, for a module with no search area or one whose
   document says nothing useful;
3. its `intro`, when it declares `FEATURE_MOD_INTRO`.

**`\local_dixeo\hook\extract_module_content`** — dispatched for module types Dixeo does
not read natively and whose search area returned nothing. The first callback that answers
wins.

**`\local_dixeo\hook\extend_course_context`** — dispatched while building a course
context, so a plugin that links courses together can have their content described
too. `add_course($courseid)` adds a whole course, `add_course($courseid, [1, 2])`
only those section numbers. Linked course sections follow the same visibility rules
as the main course, so the callback only has to decide which courses the user is
entitled to. Their files are not sent to the Dixeo file index.

```php
// db/hooks.php
$callbacks = [
    [
        'hook' => \local_dixeo\hook\extract_module_content::class,
        'callback' => [\your_plugin\dixeo::class, 'extract_module_content'],
    ],
    [
        'hook' => \local_dixeo\hook\extend_course_context::class,
        'callback' => [\your_plugin\dixeo::class, 'extend_course_context'],
    ],
];

// classes/dixeo.php
public static function extract_module_content(\local_dixeo\hook\extract_module_content $hook): void {
    if ($hook->cm->modname === 'yourmodule') {
        $hook->set_content(your_plugin_get_text($hook->cm->instance));
    }
}

public static function extend_course_context(\local_dixeo\hook\extend_course_context $hook): void {
    foreach (your_plugin_linked_courses($hook->courseid) as $linked) {
        $hook->add_course($linked->courseid, $linked->sectionnums);
    }
}
```

# Support

For documentation, licensing or technical support:

**Dixeo**

https://www.dixeo.com

support@dixeo.com

# License

Copyright © Edunao SAS

Licensed under the GNU General Public License v3.0 or later.
