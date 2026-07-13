<?php
// StaticPageSeoTags.php - Comprehensive SEO meta tags for static pages
// Includes modern search engine, social, and AI/LLM discovery tags
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__, 2) . '/config/setPath.php';

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

$currentPage = basename($_SERVER['REQUEST_URI'], '?');
$currentPage = str_replace('/', '', $currentPage);
if (empty($currentPage)) $currentPage = 'home';

$requestUri = $_SERVER['REQUEST_URI'];
$hasCustomMetaTags = false;

if (preg_match('/^\/blog\/[^\/]+/', $requestUri)) {
    $hasCustomMetaTags = true;
}

if (preg_match('/^\/updates\/\d+/', $requestUri)) {
    $hasCustomMetaTags = true;
}

if ($hasCustomMetaTags) {
    return;
}

$siteName = "Yµn ^…^ ƒ(x) Personal Website";
$brandName = "Yµn ^…^ ƒ(x) aka. Yunus Emre Vurgun";
$ogImage = FULL_BASE_PATH . "assets/images/yunus-emre-vurgun-portrait.jpg";
$pageAuthor = $brandName;
$canonicalUrl = FULL_BASE_PATH . ($currentPage === 'home' ? '' : $currentPage);
$currentDate = date('c');

$pageTitle = "$brandName - Personal Website";
$pageDescription = "Personal website of Yunus Emre Vurgun, a software developer and IT specialist. Explore my portfolio, blog, and projects in web development, programming, and technology.";
$pageKeywords = "Yunus Emre Vurgun, software developer, IT specialist, web development, programming, portfolio, blog, technology";
$pageType = "website";

switch($currentPage) {
    case 'home':
        $pageTitle = "Yunus Emre Vurgun - Software Developer & IT Specialist | Yµn ^…^ ƒ(x)";
        $pageDescription = "Software developer and IT specialist at ASP Otomasyon A.Ş. in Istanbul. Specializing in computational intelligence, operational technology, AI/ML systems, and industrial automation. Explore my portfolio, blog, and open-source projects.";
        $pageKeywords = "Yunus Emre Vurgun, software developer Istanbul, IT specialist Turkey, computational intelligence, operational technology, OT security, industrial automation, AI/ML engineer, PHP developer, full-stack developer, personal portfolio";
        break;
    case 'about':
        $pageTitle = "About Yunus Emre Vurgun | Software Developer & IT Specialist";
        $pageDescription = "Background, education, and career of Yunus Emre Vurgun — software developer at ASP Otomasyon A.Ş. Studies at Illinois Institute of Technology and Beykoz University. Expertise in AI/ML, industrial automation, and web systems.";
        $pageKeywords = "about Yunus Emre Vurgun, software developer education, IT specialist career, Illinois Institute of Technology, Beykoz University, ASP Otomasyon, Istanbul developer, certifications";
        break;
    case 'portfolio':
        $pageTitle = "Portfolio | Project Archive - Yµn ^…^ ƒ(x)";
        $pageDescription = "Chronological archive of software development projects spanning AI/ML systems, industrial automation, web applications, and operational technology infrastructure.";
        $pageKeywords = "Yunus Emre Vurgun portfolio, software projects, web development portfolio, industrial automation projects, machine learning projects, PHP projects, open source, GitHub";
        $pageType = "profilePage";
        break;
    case 'gallery':
        $pageTitle = "Gallery | Visual Archive - Yµn ^…^ ƒ(x)";
        $pageDescription = "Visual archive of photography, design work, and creative technical explorations from personal and professional projects.";
        $pageKeywords = "Yunus Emre Vurgun gallery, photography portfolio, visual design, creative projects, technical photography";
        $pageType = "profilePage";
        break;
    case 'contact':
        $pageTitle = "Contact - Yµn ^…^ ƒ(x) | Get In Touch";
        $pageDescription = "Reach out for collaboration on software development, IT consulting, or technology projects. Open to freelance, contract, and full-time opportunities.";
        $pageKeywords = "contact Yunus Emre Vurgun, software development collaboration, IT consulting, freelance developer Istanbul, technology consultation, project inquiry";
        break;
    case 'blog':
        $pageTitle = "Blog | Technical Articles - Yµn ^…^ ƒ(x)";
        $pageDescription = "Technical articles and insights on software development, AI/ML, industrial automation, operational technology, and systems architecture by Yunus Emre Vurgun.";
        $pageKeywords = "Yunus Emre Vurgun blog, software development articles, AI/ML tutorials, industrial automation blog, OT security, PHP development, technology insights, coding tutorials";
        $pageType = "blog";
        break;
    case 'travel':
        $pageTitle = "Travel Map | Locations & Distances - Yµn ^…^ ƒ(x)";
        $pageDescription = "Interactive 3D globe and 2D map tracking travel locations and distances from Istanbul HQ. Visual exploration of countries visited and miles traveled.";
        $pageKeywords = "Yunus Emre Vurgun travel, travel map, countries visited, distance calculator, Istanbul travel, 3D globe, Leaflet map, travel tracker";
        break;
    case 'updates':
        $pageTitle = "Updates | Latest News - Yµn ^…^ ƒ(x)";
        $pageDescription = "Latest project updates, achievements, certifications, and news from Yunus Emre Vurgun's work in software development and technology.";
        $pageKeywords = "Yunus Emre Vurgun updates, project news, software development updates, tech news, certifications, achievements, career updates";
        $pageType = "collectionPage";
        break;
    case 'yunobot':
        $pageTitle = "YunoBot | AI Assistant - Yµn ^…^ ƒ(x)";
        $pageDescription = "Browser-based AI chat assistant using local ML models (Transformers.js) with hybrid regex and semantic embedding understanding. No server-side processing — all inference runs on-device.";
        $pageKeywords = "YunoBot, browser AI, local LLM, Transformers.js, on-device ML, semantic chatbot, AI assistant, client-side AI, ML inference browser";
        break;
    case 'search':
        $pageTitle = "Search - Yµn ^…^ ƒ(x)";
        $pageDescription = "Search across all content on the site including blog posts, portfolio projects, gallery images, and updates using hybrid keyword and semantic search.";
        $pageKeywords = "site search, semantic search, blog search, portfolio search, Yunus Emre Vurgun content";
        break;
    case 'more':
        $pageTitle = "More - Yµn ^…^ ƒ(x)";
        $pageDescription = "Additional pages, legal information, and resources including privacy policy, terms of use, and cookie policy.";
        $pageKeywords = "legal, privacy policy, terms of use, cookie policy, additional resources";
        break;
}

$shortTitle = explode(' - ', $pageTitle)[0];
$ogType = $pageType;
?>
<title><?php echo htmlspecialchars($pageTitle); ?></title>
<meta name="title" content="<?php echo htmlspecialchars($shortTitle); ?>">
<meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($pageKeywords); ?>">
<meta name="author" content="<?php echo htmlspecialchars($pageAuthor); ?>">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="bingbot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">

<!-- AI Crawler Tags -->
<meta name="ai:bot" content="index, follow">
<meta name="ai:translator" content="no">
<meta name="x-robots-tag" content="index, follow, noarchive">

<!-- Language & Locale -->
<meta name="language" content="English">
<meta name="locale" content="en_US">
<meta name="charset" content="UTF-8">

<!-- Crawling & Syndication -->
<meta name="revisit-after" content="3 days">
<meta name="distribution" content="global">
<meta name="rating" content="general">
<meta name="coverage" content="Worldwide">

<!-- Geo Tags -->
<meta name="geo.region" content="TR-34">
<meta name="geo.placename" content="Istanbul">
<meta name="geo.position" content="41.0082;28.9784">
<meta name="ICBM" content="41.0082, 28.9784">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="<?php echo $ogType; ?>">
<meta property="og:url" content="<?php echo $canonicalUrl; ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<meta property="og:image" content="<?php echo $ogImage; ?>">
<meta property="og:image:alt" content="Yunus Emre Vurgun - Software Developer & IT Specialist">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:site_name" content="<?php echo $siteName; ?>">
<meta property="og:locale" content="en_US">
<meta property="og:locale:alternate" content="tr_TR">
<meta property="og:updated_time" content="<?php echo $currentDate; ?>">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="<?php echo $canonicalUrl; ?>">
<meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<meta name="twitter:image" content="<?php echo $ogImage; ?>">
<meta name="twitter:creator" content="@yemrevu">
<meta name="twitter:site" content="@yemrevu">
<meta name="twitter:label1" content="Written by">
<meta name="twitter:data1" content="Yunus Emre Vurgun">
<meta name="twitter:label2" content="Location">
<meta name="twitter:data2" content="Istanbul, Turkey">

<!-- Pinterest -->
<meta name="p:domain_verify" content="">

<!-- Canonical URL -->
<link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">

<!-- Mobile & PWA -->
<meta name="format-detection" content="telephone=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Yµn ^…^ ƒ(x)">
<meta name="application-name" content="<?php echo $siteName; ?>">
<meta name="msapplication-tooltip" content="<?php echo $siteName; ?>">
<meta name="msapplication-starturl" content="<?php echo FULL_BASE_PATH; ?>">
<meta name="msapplication-TileColor" content="#1B1714">

<!-- Sitemap & LLM guidance -->
<link rel="sitemap" type="application/xml" title="Sitemap" href="<?php echo FULL_BASE_PATH; ?>sitemap.xml">
<link rel="alternate" type="text/plain" title="LLMs" href="<?php echo FULL_BASE_PATH; ?>llms.txt">
<link rel="alternate" type="application/ld+json" title="LLMs" href="<?php echo FULL_BASE_PATH; ?>llms.txt">

<!-- Preconnect for performance -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net">

<!-- DNS prefetch for external resources -->
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://fonts.gstatic.com">
<link rel="dns-prefetch" href="https://yunusemrevurgun.com">

<!-- Resource hints -->
<link rel="preconnect" href="https://yunusemrevurgun.com">

<!-- Structured Data (JSON-LD) -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "Person",
            "@id": "<?php echo FULL_BASE_PATH; ?>#person",
            "name": "Yunus Emre Vurgun",
            "alternateName": "Yµn ^…^ ƒ(x)",
            "url": "<?php echo FULL_BASE_PATH; ?>",
            "image": {
                "@type": "ImageObject",
                "url": "<?php echo $ogImage; ?>",
                "width": 1200,
                "height": 630
            },
            "description": "<?php echo htmlspecialchars($pageDescription); ?>",
            "jobTitle": "Software Developer & IT Specialist",
            "worksFor": {
                "@type": "Organization",
                "name": "ASP Otomasyon A.Ş.",
                "url": "https://asp.com.tr"
            },
            "address": {
                "@type": "PostalAddress",
                "addressLocality": "Istanbul",
                "addressRegion": "Istanbul",
                "addressCountry": "TR"
            },
            "sameAs": [
                "https://github.com/yunusemrejr",
                "https://linkedin.com/in/yunus-emre-vurgun-49ba9a177",
                "https://odysee.com/@programmingwithyunusemrevu7222",
                "https://mastodon.social/@yunusemrevurgn",
                "https://x.com/yemrevu",
                "https://bsky.app/profile/yunusemrevurgun.bsky.social",
                "https://www.threads.com/@yemrevu",
                "https://instagram.com/yemrevu",
                "https://www.youtube.com/@yunusemrevurgun1"
            ],
            "knowsAbout": [
                "Software Development",
                "Web Development",
                "IT Operations",
                "Computational Intelligence",
                "Operational Technology",
                "Machine Learning",
                "Industrial Automation",
                "OT Security",
                "PHP",
                "JavaScript",
                "TypeScript",
                "Python",
                "Django",
                "Neo4j",
                "MySQL",
                "PostgreSQL",
                "Transformers.js",
                "Three.js",
                "React",
                "Node.js",
                "Linux",
                "Docker",
                "CI/CD"
            ],
            "alumniOf": [
                {"@type": "EducationalOrganization", "name": "Illinois Institute of Technology"},
                {"@type": "EducationalOrganization", "name": "Beykoz University"},
                {"@type": "EducationalOrganization", "name": "Anadolu University"},
                {"@type": "EducationalOrganization", "name": "Bahcesehir University"}
            ],
            "award": [
                "IELTS Academic Score 7.5",
                "Fortinet OT Security Architect Training",
                "Google Cloud Generative AI",
                "Intel AI Essentials"
            ]
        },
        {
            "@type": "WebSite",
            "@id": "<?php echo FULL_BASE_PATH; ?>#website",
            "url": "<?php echo FULL_BASE_PATH; ?>",
            "name": "<?php echo $siteName; ?>",
            "alternateName": "Yunus Emre Vurgun Personal Website",
            "description": "Personal website of Yunus Emre Vurgun — software developer and IT specialist focusing on computational intelligence, operational technology, and AI/ML systems.",
            "publisher": {"@id": "<?php echo FULL_BASE_PATH; ?>#person"},
            "potentialAction": {
                "@type": "SearchAction",
                "target": "<?php echo FULL_BASE_PATH; ?>search?q={search_term_string}",
                "query-input": "required name=search_term_string"
            },
            "inLanguage": "en-US"
        },
        {
            "@type": "WebPage",
            "@id": "<?php echo $canonicalUrl; ?>#webpage",
            "url": "<?php echo $canonicalUrl; ?>",
            "name": "<?php echo htmlspecialchars($pageTitle); ?>",
            "description": "<?php echo htmlspecialchars($pageDescription); ?>",
            "isPartOf": {"@id": "<?php echo FULL_BASE_PATH; ?>#website"},
            "about": {"@id": "<?php echo FULL_BASE_PATH; ?>#person"},
            "primaryImageOfPage": {
                "@type": "ImageObject",
                "url": "<?php echo $ogImage; ?>"
            },
            "datePublished": "<?php echo $currentDate; ?>",
            "dateModified": "<?php echo $currentDate; ?>",
            "inLanguage": "en-US"
        },
        {
            "@type": "Organization",
            "@id": "<?php echo FULL_BASE_PATH; ?>#organization",
            "name": "<?php echo $siteName; ?>",
            "url": "<?php echo FULL_BASE_PATH; ?>",
            "logo": {
                "@type": "ImageObject",
                "url": "<?php echo FULL_BASE_PATH; ?>assets/images/favicon-pfp.png"
            },
            "sameAs": [
                "https://github.com/yunusemrejr",
                "https://linkedin.com/in/yunus-emre-vurgun-49ba9a177",
                "https://x.com/yemrevu",
                "https://instagram.com/yemrevu",
                "https://www.youtube.com/@yunusemrevurgun1"
            ],
            "founder": {"@id": "<?php echo FULL_BASE_PATH; ?>#person"}
        },
        {
            "@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": 1,
                    "name": "Home",
                    "item": "<?php echo FULL_BASE_PATH; ?>"
                },
                {
                    "@type": "ListItem",
                    "position": 2,
                    "name": "<?php echo htmlspecialchars($currentPage === 'home' ? 'Home' : ucfirst($currentPage)); ?>",
                    "item": "<?php echo $canonicalUrl; ?>"
                }
            ]
        }
    ]
}
</script>

<!-- RSS / Atom Feed -->
<link rel="alternate" type="application/rss+xml" title="Blog RSS Feed" href="<?php echo FULL_BASE_PATH; ?>feed/blog.xml">
<link rel="alternate" type="application/atom+xml" title="Blog Atom Feed" href="<?php echo FULL_BASE_PATH; ?>feed/blog.atom">
