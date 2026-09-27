<?php

/*
 * The founder's writing guide and the default closing call to action — the
 * ONE copy (see docs/global/BLOG.md). Read by the admin editor
 * (GET /v1/admin/blog/guide), the public article (PublicBlogPostResource fills
 * a post's empty cta_* from here), and Claude via the MCP connector
 * (GET /v1/connector/blog/guide). Both go through App\Support\BlogGuide.
 */
return [
    'voice' => [
        'Thoughtful, direct and human.',
        'Written for Malaysian business owners and founders, in clear English.',
        'Focus on UI/UX, digital experiences and custom systems that make things feel simpler for people.',
        'No agency buzzwords, exaggerated claims or aggressive sales language.',
    ],

    'structure' => [
        'Opening hook — the introduction',
        'The problem or question',
        'Why it matters',
        'Two to four practical sections',
        'Key takeaway',
        'Gentle invitation to get in touch — the closing CTA',
    ],

    'cta_defaults' => [
        'heading' => 'Have something like this in mind?',
        'body' => 'I’m always open to a conversation about what that could look like for your business.',
        'label' => 'Get in touch',
        'url' => '/contact',
    ],
];
