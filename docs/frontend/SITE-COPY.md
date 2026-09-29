# Site copy — public pages

Every line of copy a visitor reads on the public site, page by page, in reading order, as rendered on 2026-09-29. Edit the right-hand text here, then hand it back to be applied.

- **⚠** marks a line that reads as generic or AI-written, with the reason. Unflagged lines are fine as they are (edit them anyway if you like).
- **Admin** marks text that comes from the admin, not code — change it there (services & packages, projects, blog posts, testimonials, mockups).
- Titles ≤ 60 characters and descriptions ≤ 155 are the search-result limits — keep them if you rewrite those.

## Patterns to cut across the whole site

These are what make copy read as AI-written. Most flags below are one of them.

1. **Abstract virtue words** — *meaningful, intentional, thoughtful, seamless, effortless, timeless, premium, senior craft, human-centered*. They describe how you want to be seen, not what the client gets. Replace each with a concrete fact: a result, a number, a named client type, a time frame.
2. **"Not just X — it's Y"** — *"not just a business"*, *"don't just work — they endure"*, *"Not tourism — immersion"*. One per site at most.
3. **Rule-of-three lists** — *"design, systems thinking, travel, and constant self-evolution"*, *"Movement. Structure. Forward momentum."* Real sentences rarely come in neat threes.
4. **Em-dash chains** — nearly every paragraph has one or two. Use a full stop or comma instead in most places.
5. **Inconsistent voice** — the site switches between *I* (contact, quote, about, services contact block) and *we* (services intro, partners, refer). Pick one. As a founder-led studio, *I* for anything about the work and *Axel Nova* for company facts is honest and reads more human.
6. **Audience drift** — the homepage and services now speak to Malaysian SMEs (clinics, hotels, shops), but several lines still pitch *fintech, SaaS, senior craft* — a different buyer.

---

## Homepage `/`

Files: [pages/public/index.vue](../../frontend/app/pages/public/index.vue), [components/public/HeroEpoch.vue](../../frontend/app/components/public/HeroEpoch.vue), [components/public/ReferralBand.vue](../../frontend/app/components/public/ReferralBand.vue)

| Block | Copy |
|---|---|
| Title | Web Development Studio in Kuala Lumpur \| Axel Nova Ventures |
| Description | Axel Nova Ventures is a digital studio in Kuala Lumpur that designs and builds websites, booking portals and custom business systems for Malaysian SMEs. |
| Eyebrow | Web design & development studio · Kuala Lumpur |
| H1 | Crafted by design. Built to last. |
| Lede | Axel Nova Ventures is a web design and development studio in Kuala Lumpur. I design and build websites, customer portals and custom systems for businesses across Malaysia: clear to use, quick to load, and fully yours once it's live. |
| Button | Start a Project |
| Stats | 7+ Years building · 3 Years in industry · 10+ Projects shipped · 2 Degrees pursuing ⚠ *"Degrees pursuing" is unclear to a client and doesn't help them decide. Swap for something a buyer cares about (e.g. "1 working day reply time", "100% code handed over").* |
| Section eyebrow / H2 | Selected work / Featured projects. |
| Sub | A few live builds — hover a card to visit the real site. *(touch: "swipe left to see more, tap a card to visit the real site.")* |
| Cards | **Admin** — projects |
| Section eyebrow / H2 | Client previews / Featured mockups. |
| Sub | Live prototypes built for clients — click a card for an instant in-page preview. |
| Section eyebrow / H2 | From the blog / Latest notes. |
| Sub | Thoughts on interfaces, systems and the decisions behind them — written for business owners and founders. |
| Referral H2 | No project of your own? Refer one instead. |
| Referral body | You probably know a business owner who keeps saying they need a website, a system, an app. Make the introduction — we scope, build, and deliver, and you earn a commission when the project closes. |
| Referral footnote | *5% for a name, up to 15% for a closed referral, capped at RM1,500 per referral · smaller projects earn a flat RM150. |
| Referral steps | Send the intro — Tell us who they are and what they need — it takes about two minutes. / We do the building — We reach out, scope, and deliver to a senior standard. You stay in the loop. ⚠ *"senior standard" is vague.* / You collect the commission — Once the project is signed and paid, your cut lands within 14 working days. |
| Closing CTA | Have a project in mind? |
| Closing body | Let's design something premium together. Fintech, SaaS, or a product that needs senior craft. ⚠ *Abstract ("premium", "senior craft") and pitches fintech/SaaS right after the page spoke to SMEs. Say what happens next instead, e.g. "Tell me what you need and I'll reply within a working day with a rough scope and price."* |
| Closing button | Let's talk |

## About `/about`

File: [pages/public/about.vue](../../frontend/app/pages/public/about.vue)

| Block | Copy |
|---|---|
| Title | About Ahmad Baihaqie, Web Developer \| Axel Nova Ventures |
| Description | Meet Ahmad Baihaqie, the UI/UX-focused software engineer behind Axel Nova Ventures, designing and building websites and systems from Kuala Lumpur. |
| Eyebrow / H1 | About / The builder behind the screen. |
| Bio 1 | I'm Qie, a UI/UX-focused software engineer building products where design clarity meets real-world functionality. ⚠ *"where X meets Y" is a stock AI construction. Also: the page title says Ahmad Baihaqie, the bio says Qie — introduce both once ("I'm Ahmad Baihaqie — most people call me Qie").* |
| Bio 2 | I specialise in transforming complex systems into intuitive digital experiences, especially within fintech platforms, admin systems, and scalable web applications. My work sits between interface design, frontend engineering, and system thinking, allowing me to bridge user needs with technical execution in a seamless and thoughtful way. ⚠ *"transforming… into intuitive digital experiences", "bridge… in a seamless and thoughtful way" — pure filler. Name one real thing you built and what changed for its users.* |
| Bio 3 | I care deeply about how things feel in use. Every interaction, every flow, and every detail matters. To me, great products should feel effortless, almost invisible. ⚠ *Rule of three + "effortless". Keep the first sentence, cut the rest or give one example.* |
| Bio 4 | I'm constantly exploring the relationship between AI, human-centered technology, and digital experiences. axelnova is where I shape those ideas into something meaningful, both functionally and emotionally. ⚠ *"something meaningful, both functionally and emotionally" says nothing.* |
| Bio 5 | Outside of work, I'm drawn to growth, travel, and experiences that challenge perspective. I'm driven by the belief that life shouldn't be lived on autopilot. I want to build meaningful things, experience the world fully, and continuously evolve into a better version of myself through both the work I create and the life I choose to live. ⚠ *Three abstract lists in a row. One specific detail (a place, a habit) beats all of it.* |
| Skills H3 | Skills. — Frontend / Backend / Data & Queue / Infrastructure / Tools |
| Story eyebrow / H3 | My Story / How I got here. |
| Story 1 | I grew up with a quiet obsession — how do things work, and how could they work better? That curiosity led me to an unconventional degree: Islamic Studies and Information Technology at Universiti Malaya. Not the obvious path for a software engineer. The best decision I ever made. |
| Story 2 | That combination gave me something most engineers don't have — a framework for thinking about the human side of technology. Jurisprudence taught me to reason carefully under uncertainty. Islamic philosophy taught me that knowledge is service. Those ideas still show up in how I build today. *(Good — specific. Consider one example of "how".)* |
| Story 3 | My first real job wasn't at a tech company. It was a print shop in Johor — designing brochures and fixing computers for people with real, ordinary problems. I learned there that the gap between a design and the person it serves is the most important gap to close. *(Good — the print shop is the most human detail on the site.)* |
| Story 4 | Everything since has been about closing that gap. Building things that don't just work, but feel like they were made for the person using them. ⚠ *"don't just work, but…"* |
| Beliefs | Design is thinking made visible. / Good products respect the people who use them. / The best engineers understand people, not just systems. / Curiosity is the only sustainable competitive advantage. ⚠ *Poster slogans. Keep one you'd actually defend in a meeting.* |
| Timeline H3 | Life in chapters / How it unfolded. |
| 2019 | The Beginning — Foundation at Universiti Malaya — Chose an unconventional path — Islamic Studies and Information Technology. Not despite the breadth, but because of it. Faith and technology were never opposites in my mind. |
| 2020 | First Real Work — Designing for Real People — Started at a print shop in Johor — designing brochures and fixing computers for ordinary people with real problems. Learned that the gap between a design and the person it serves is the most important gap to close. ⚠ *Repeats Story 3 word for word.* |
| 2023 | Graduating — First Class. Two Worlds. — Graduated with First Class Honours. The degree wasn't just a credential — it was proof that bridging two disciplines creates something neither alone can. ⚠ *"wasn't just… it was proof"* |
| Fintech | Into Fintech — Building Systems That Can't Fail — Joined Fiuu — payments infrastructure moving real money for real merchants. Learned what it means to build UI where errors carry real consequences. Every pixel has weight. ⚠ *"Every pixel has weight" — cut; the sentence before already says it.* |
| Now | In Progress — Researching. Building. Becoming. ⚠ *Triple fragment.* — Postgraduate research on AI and people at UPM. Building axelnova as a space to explore what I actually believe about technology. Still asking more questions than I can answer. |
| Ambitions H3 | Ambitions / What I'm after. |
| 1 | A product that outlasts me — Build something people rely on — not because they have to, but because it genuinely improves how they live or work. A product with a soul. ⚠ *"A product with a soul"* |
| 2 | See the world with intention — 50 countries before I turn 40. Not tourism — immersion. Every place I visit reshapes how I think and what I build. ⚠ *"Not tourism — immersion". The 50-by-40 goal is great — keep it.* |
| 3 | Write something worth keeping — A book. A body of essays. Thinking that survives the screen and lasts beyond the moment it was written. |
| 4 | Lead a team worth following — Build an organisation where craft is valued, people grow, and the work actually matters. Culture is a product too. ⚠ *"Culture is a product too"* |
| 5 | Understand AI before it defines us — I want to understand what happens to human identity when machines become collaborators — and help shape that answer before someone else does. |
| 6 | A life built on intention — Not defined by titles or metrics — by the quality of the work, the depth of the relationships, and the spaces I choose to inhabit. ⚠ *Abstract; overlaps with Bio 5.* |
| Closing note | A note. — I build things that hold up — under real load, real deadlines, and real users. Not everything I make is finished. But everything I start, I care about. *(Good — honest.)* |

## Company `/company`

File: [pages/public/company.vue](../../frontend/app/pages/public/company.vue). This page carries the most generic copy on the site.

| Block | Copy |
|---|---|
| Title | Our Studio — Axel Nova Ventures |
| Description | How Axel Nova Ventures works: design-first thinking, intentional engineering, and human-centered digital products — from first sketch to production. ⚠ *Three abstractions; no service or place.* |
| H1 | Technology should not only function well — it should feel meaningful. ⚠ *"not only… it should feel meaningful"* |
| Sub | Built in Malaysia. Designed for the world. A company shaped by design, systems thinking, and a quiet refusal to build anything ordinary. ⚠ *"quiet refusal" + list of three.* |
| Origin H2 | Born from a simple belief. |
| Origin 1 | Axel Nova Ventures was born from a simple belief: technology should not only function well — it should feel meaningful. ⚠ *Repeats the H1 verbatim.* |
| Origin 2 | Built by Qie, a UI/UX-focused software engineer from Malaysia, the company reflects a journey shaped by design, systems thinking, travel, and constant self-evolution. Years spent building fintech platforms and digital experiences revealed one thing clearly: most systems are created to work, but very few are created to truly connect with people. ⚠ *"journey shaped by…", "truly connect with people".* |
| Origin 3 | Axel Nova Ventures exists to bridge that gap. ⚠ *"bridge that gap" appears on About too.* |
| At a glance | Registered Name · Registration No. 202603119899 (CA0420977-U) · Established 2026 · Headquarters Kuala Lumpur, Malaysia · Focus Design & Technology · Stage Early — building boldly ⚠ *"building boldly"; "Design & Technology" is vague — say "Websites & business systems".* |
| Name H2 | The Name / Every word carries a meaning. |
| Axel | Movement. Structure. Forward momentum. — The unseen core that keeps things turning — steady, intentional, and always in motion. An axis isn't visible, but everything revolves around it. ⚠ *Triple fragments ×2.* |
| Nova | New beginnings. Expansion. Transformation. — A nova is a star releasing tremendous energy — a moment of brilliant change. It represents the drive to start fresh, grow boldly, and transform continuously. ⚠ *Same pattern.* |
| Together | Together, Axel Nova reflects a mindset of continuous growth — building better systems, better experiences, and ultimately, a better life. |
| Vision H2 | Vision / More than a digital company. ⚠ |
| Vision body | Axel Nova Ventures is intended to become a long-term platform for ideas, products, and ventures that combine technology, design, and human experience. From software and digital platforms to future creative and business ventures — built with the belief that meaningful products should feel effortless, intentional, and timeless. ⚠ *"effortless, intentional, and timeless".* |
| Pillars | Design-first thinking — Every system begins with understanding the person who will use it — not the technical constraints. / Engineering with intention — Clean, scalable systems that don't just work — they endure. ⚠ / Human-centered by default — Technology built around how people actually live, think, and feel. ⚠ |
| Philosophy H2 | Philosophy / Shaped by the world. |
| Philosophy 1 | Traveling across different cities, cultures, airports, hotels, and unfamiliar environments shaped the way Qie sees the world. Every journey reinforced the same idea: great experiences are never accidental. |
| Philosophy 2 | They are carefully designed, emotionally aware, and thoughtfully executed. That same philosophy now carries into every product and system built under Axel Nova Ventures. ⚠ |
| Philosophy 3 | At its core, the company represents a quiet refusal to live an average life — building with intention, creating things that matter, exploring beyond comfort zones, and proving that technology can still feel human in a world increasingly driven by automation and speed. ⚠ *Second "quiet refusal"; four-item list.* |
| Quote | "Great experiences are never accidental — they are carefully designed, emotionally aware, and thoughtfully executed." The philosophy behind every build. ⚠ *Third time this sentence appears on the page.* |
| Closing | A larger vision — Axel Nova Ventures is not just a business. / It is the beginning of a larger vision. / One that grows together with the person building it. ⚠ *"not just a business".* |

## Services `/services`

File: [pages/public/services/index.vue](../../frontend/app/pages/public/services/index.vue)

| Block | Copy |
|---|---|
| Title | Web Design & Development Services in Malaysia \| Axel Nova |
| Description | Web design and development for Malaysian businesses, from a one-page site to a full custom system. Clear packages in RM and an instant online estimate. |
| Eyebrow / H1 | Services / Web design and development, with clear pricing. |
| Intro | From a focused landing page to a custom business system, we design and build digital experiences for businesses across Malaysia. Explore our services, view packages in ringgit, and get an estimate in minutes. *(uses "we" — the contact block below uses "I")* |
| Tabs, descriptions, packages | **Admin** — service categories and packages |
| Estimator | Estimator / Estimate your project. / Adjust the inputs and watch ballpark cost and timeline update live. / Rough estimate. Final scope is agreed in writing before any work begins. |
| Process | Process / How we work together. — Discovery: Scope, goals, success metrics. · Design: Figma flows + component system. · Build: Vue/Nuxt build, API integration, QA. · Handover: Walkthrough, docs, support. *(Build step is tech jargon for an SME reader — e.g. "Build: I build it, connect your tools, and test it on real devices.")* |
| Contact | Contact / Let's talk. / Pick the channel that suits you. I usually reply within a working day. — WhatsApp: Fastest for quick questions and project chats. · Email: Best for briefs, scope docs, and longer conversations. · Call: For urgent or complex scope, book a quick voice call. |

## Service detail pages `/services/{slug}`

File: [pages/public/services/[slug].vue](../../frontend/app/pages/public/services/[slug].vue) (`enrichmentBySlug`). Name and description fall back to the admin when a slug has no entry.

| Slug | H1 / hero title | Hero subtitle |
|---|---|---|
| web-presence | Website development for Malaysian businesses. (lead: Web apps and sites that work — and keep working.) | Production-grade builds for startups and SMEs in Malaysia. Vue, Nuxt, and Laravel. Clean code, real performance, and full ownership on day one. ⚠ *"Production-grade", stack names for an SME reader.* |
| admin-portal | Admin dashboards your team will actually use. | Custom admin panels, internal tools, and SaaS dashboards. Built for operators who measure productivity in clicks saved. |
| ui-ux-frontend | Design that ships — because the designer codes too. | Product, interface, and interaction design for SaaS and fintech. From research to design system to dev handover, by one person who's owned both sides. |
| digital-marketing | Creative that looks premium on every channel. ⚠ *"premium"* | From single social posts to full festive campaigns and brand refresh kits. Designed for Malaysian SMEs that want to look serious — without paying agency retainer prices. |
| booking-portal | Stop managing bookings in WhatsApp. *(Good — concrete pain.)* | *(see file)* |
| ecommerce | Own your storefront. Own your customer. | *(see file)* |

Each also has 4 "What you get" cards, a stack list and FAQs in the same file.

## Projects `/projects`

File: [pages/public/projects/index.vue](../../frontend/app/pages/public/projects/index.vue)

| Block | Copy |
|---|---|
| Title | Web Development Portfolio \| Axel Nova Ventures, Malaysia |
| Description | Websites, web apps and business systems designed and built by Axel Nova Ventures in Kuala Lumpur. See the live projects and the stack behind each. |
| Eyebrow / H1 | Project registry / Everything I'm building. |
| Sub | A growing index of shipped products, ongoing builds, and experiments. Filter by stack or status. |
| Project cards | **Admin** — projects. ⚠ *Several are dev tools (JSON Beautifier, API Vault, Parfum API) — useful for a developer audience, noise for an SME buyer. Consider featuring client work first.* |

## Blog `/blog`

File: [pages/public/blog/index.vue](../../frontend/app/pages/public/blog/index.vue)

| Block | Copy |
|---|---|
| Title | Blog: Websites & Business Systems in Malaysia \| Axel Nova |
| Description | Plain-English notes on websites, custom systems and UX for Malaysian business owners, so the next tech decision is an easier one. |
| Eyebrow / H1 | Blog / Notes from the workbench. |
| Sub | Thoughts on interfaces, systems and the decisions behind them — written for business owners and founders. |
| Posts | **Admin** — blog |

## Contact `/contact`

File: [pages/public/contact.vue](../../frontend/app/pages/public/contact.vue)

| Block | Copy |
|---|---|
| Title | Contact a Web Developer in Kuala Lumpur \| Axel Nova Ventures |
| Description | Planning a website or custom system? Tell me about it. Based in Kuala Lumpur, working with businesses across Malaysia. Replies within one working day. |
| Eyebrow / H1 | Contact / Let's connect. |
| Sub | Whether you have a project in mind, a question, or just want to say hello, I'm happy to hear from you. |
| Form | Name · Email · Subject (Project inquiry / Collaboration / General question / Feedback / Other) · Message · **Send message →** |
| Side | Available for selected collaborations ⚠ *"selected collaborations" sounds like you might turn them away — say "Taking new projects for [month]".* / Based in Kuala Lumpur, Malaysia. Open to remote and global projects. Typically replies within one working day. / Or reach out directly — WhatsApp +60 18-317 3103 · Email baihaqie@axelnova.tech · Phone / For project enquiries, sharing a brief or scope document helps us get started faster. *("us" — rest of page is "I")* |

## Partner Program `/partners`

File: [pages/public/partners/index.vue](../../frontend/app/pages/public/partners/index.vue)

| Block | Copy |
|---|---|
| Title | Partner Program — Axel Nova Ventures |
| Description | Refer a business to Axel Nova and earn up to 15% when the project closes. A professional referral program for property agents, freelancers, consultants, and agencies. *(166 characters — over the limit)* |
| H1 | Refer a business. Earn up to 15%*. |
| Sub | Know a company or property owner who needs design-led software? Refer them to Axel Nova. We scope, build, and deliver — you earn a commission when the project closes. ⚠ *"design-led software"* |
| Who H2 | Who it's for / Built for people with the right rooms. — You don't need to be technical — you just need to know a business that's ready to build. |
| Audiences | Property agents — You meet owners and developers who need a serious digital presence. · Freelancers & designers — You take on work that needs senior frontend or full builds you don't cover. · Consultants & accountants — Your SME clients keep asking who builds their software and tools. · Agencies & studios — You have overflow or out-of-scope projects worth handing off. · Creators & community builders — Your audience includes founders and business owners who need to ship. · Anyone wanting a side income — You know business owners in your circle — turn an introduction into income on the side. |
| How H2 | How it works / Three steps, no friction. — Refer a business: Tell us who they are and what they need. It takes about two minutes. · We take it from there: We reach out, scope the project, and deliver to a senior standard. You stay in the loop. · You get paid: When the project is signed and paid, your commission is paid within 14 working days. |
| Commission H2 | Commission / The more you bring, the more you earn. — Three tiers, based on how far you carry the referral. Commission is paid on the final project value. — Cold lead 5%: You pass us their name and contact. Axel Nova handles the entire conversation. · Warm intro 10%: You introduce us personally and vouch for Axel Nova to the business. · Closed referral up to 15%*: You've already talked it through with them — we only scope and deliver. |
| Footnote | *Commission tiers apply to Professional-tier projects and above, from RM3,000, and payouts are capped at RM1,500 per referral. Smaller Starter-tier projects earn a flat RM150 referral fee. All commission is subject to the Partner Program terms. ⚠ *Fact check: the packages are now named Essential / Business / Premium, not Starter / Professional.* |
| What H2 | What you're referring / Design-led software, built to a senior standard. ⚠ — Axel Nova builds UI/UX design, frontend engineering, and full product builds for fintech, SaaS, and bespoke web. Premium work the business can be proud of — and that reflects well on you for making the introduction. ⚠ *"Premium", fintech/SaaS pitch.* |
| Terms H2 | The fine print, briefly / Fair terms, clearly stated. — The essentials below. Full terms are shared when a referral progresses to a project. — (6 bullets: signed and paid in full · capped at RM1,500 · first valid referral wins, 90 days · no commission on existing clients, duplicates or self-referrals · paid within 14 working days via local bank transfer · minimum payout RM150) |
| FAQ H2 | FAQ / Questions, answered. — 8 questions (Who can refer · How much can I earn · Do I have to sell anything · When and how do I get paid · Same business referred twice · What doesn't qualify · Cost or commitment · What happens after I refer) |
| Closing | Know a business that needs to build? / Refer them in two minutes — we'll take it from there. |

## Refer `/partners/refer`

File: [pages/public/partners/refer.vue](../../frontend/app/pages/public/partners/refer.vue)

| Block | Copy |
|---|---|
| Title / Description | Refer a Business — Axel Nova Partner Program / Refer a business to Axel Nova and earn up to 15% when the project closes. Takes two minutes — no cost, no commitment. |
| Eyebrow / H1 | Partner Program / Refer a business. |
| Sub | Tell us who you're referring and how to reach them. We'll take it from there — and credit you if it becomes a project. |
| Form | Your details (Full name · Email · Phone number) · The business you're referring (Business / contact name · Their email · Their phone · What do they need? Website / UI/UX design / Web app or SaaS / Not sure yet) · How well do you know them? (Just passing their contact / I'll introduce you personally / We've already discussed it) — This helps us set expectations — your commission tier is confirmed once we've spoken to them. · Anything else we should know? · Consent line · **Submit referral →** — Bank details for payouts are collected separately, only once a referral becomes a paid project. |
| Side | What you'll earn — *Commission is paid on the final project value, capped at RM1,500 per referral, within 14 working days of cleared payment. / What happens next — 1. We reach out to the business you referred, usually within 3 business days. 2. We scope and deliver the project — you stay in the loop throughout. 3. When it's signed and paid, your commission is paid within 14 working days. / Want the full picture first? Read the Partner Program overview or email baihaqie@axelnova.tech. |

## Quote `/quote`

File: [pages/public/quote/index.vue](../../frontend/app/pages/public/quote/index.vue)

| Block | Copy |
|---|---|
| Title / Description | Request a Quote — Axel Nova Ventures / Tell me about your project and I'll put together a tailored quote. Share your goals, budget, and timeline — no commitment required. |
| Eyebrow / H1 | Start a project / Tell me about your project. |
| Sub | Share a few details and I'll put together a tailored quote — scope, timeline, and pricing. No commitment required. |
| Form | About you (Full name · Company / project · Email · Phone / WhatsApp) · Your project (What are you building? Website / Dashboard / portal / Design & frontend / Web app / SaaS / Other / not sure · Budget range < RM 5k / RM 5k – 15k / RM 15k – 40k / RM 40k+ / Flexible · Timeline ASAP / 1–2 months / 3–6 months · Project details) · **Send inquiry →** — I'll review your details and reply with a tailored quote, usually within 1–2 business days. ⚠ *Contact page says "one working day" — pick one.* |
| Side | What happens next — 1. I review your details and put together a tailored quote — scope, timeline, and pricing. 2. You receive it by email to review at your own pace. No pressure. 3. If it's a fit, we book a short call to finalise scope and get started. / Not sure what you need? Browse the services to see what's possible, or just describe your idea above — I'll help you shape it. |

## Footer (every page)

File: [layouts/public.vue](../../frontend/app/layouts/public.vue)

| Block | Copy |
|---|---|
| Tagline | Building thoughtful digital experiences through design, systems, and technology. ⚠ *"thoughtful digital experiences" + list of three. E.g. "Websites and business systems for Malaysian companies, designed and built in Kuala Lumpur."* |
| Company card | Axel Nova Ventures · Registration No.: 202603119899 (CA0420977-U) · Kuala Lumpur, Malaysia |
| Status | Available for selected collaborations ⚠ *(same as Contact)* |
| Columns | Explore (nav) · Services (**Admin** — service names) · Support (Contact Us · Support Our Work · Give Feedback · Report an Issue) · Legal (Privacy Policy · Terms & Conditions · Cookie Policy · Disclaimer · Refund Policy) |
| Bottom | © 2026 Axel Nova Ventures. All rights reserved. |
