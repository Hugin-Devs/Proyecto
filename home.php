<?php
session_start();
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
// Si ya hay sesión activa, redirigir directo al panel
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servi-Job — Servicios Laborales Online</title>
    <link href="fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --navy: #0f2057;
            --blue: #1a3a8f;
            --blue-mid: #2d5be3;
            --blue-light: #3d7af5;
            --orange: #f5820d;
            --orange-dark: #d96a00;
            --orange-glow: rgba(245, 130, 13, 0.25);
            --white: #ffffff;
            --off-white: #f4f6fc;
            --text-muted: #8898bb;
            --card-bg: rgba(255,255,255,0.04);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--navy);
            color: var(--white);
            overflow-x: hidden;
        }

        /* BG */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 10% 0%, rgba(45,91,227,0.35) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 80%, rgba(245,130,13,0.18) 0%, transparent 55%),
                radial-gradient(ellipse 50% 40% at 50% 50%, rgba(15,32,87,0.9) 0%, transparent 100%);
            pointer-events: none;
            z-index: 0;
        }

        /* HEADER */
        header {
            position: sticky; top: 0; z-index: 100;
            background: rgba(15,32,87,0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            padding: 0 40px;
        }
        .nav-inner {
            max-width: 1280px; margin: 0 auto;
            display: flex; align-items: center; gap: 32px;
            height: 68px;
        }
        .logo-wrap {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none;
        }
        .logo-icon {
            width: 42px; height: 42px;
            background: linear-gradient(135deg, var(--blue-mid), var(--orange));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            box-shadow: 0 0 18px rgba(45,91,227,0.5);
        }
        .logo-text {
            font-family: 'Rajdhani', sans-serif;
            font-size: 24px; font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--white);
        }
        .logo-text span { color: var(--orange); }

        .nav-search {
            flex: 1; max-width: 520px;
            display: flex; align-items: center;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .nav-search:focus-within {
            border-color: var(--blue-light);
            box-shadow: 0 0 0 3px rgba(61,122,245,0.2);
        }
        .nav-search input {
            flex: 1; background: transparent; border: none; outline: none;
            padding: 10px 16px; color: var(--white); font-size: 14px;
        }
        .nav-search input::placeholder { color: var(--text-muted); }
        .nav-search button {
            background: var(--orange); border: none; cursor: pointer;
            padding: 10px 18px; color: white; font-size: 16px;
            transition: background 0.2s;
        }
        .nav-search button:hover { background: var(--orange-dark); }

        nav { margin-left: auto; display: flex; align-items: center; gap: 12px; }
        .btn-outline {
            padding: 9px 20px; border-radius: 7px;
            border: 1px solid rgba(255,255,255,0.2);
            color: var(--white); background: transparent;
            font-size: 14px; font-weight: 500; cursor: pointer;
            transition: all 0.2s; text-decoration: none;
        }
        .btn-outline:hover { border-color: var(--blue-light); background: rgba(61,122,245,0.1); }
        .btn-cta {
            padding: 9px 22px; border-radius: 7px;
            background: var(--orange); border: none;
            color: white; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; text-decoration: none;
            box-shadow: 0 0 20px var(--orange-glow);
        }
        .btn-cta:hover { background: var(--orange-dark); transform: translateY(-1px); }

        /* HERO */
        .hero {
            position: relative; z-index: 1;
            max-width: 1280px; margin: 0 auto;
            padding: 100px 40px 80px;
            display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;
        }
        .hero-label {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(245,130,13,0.12);
            border: 1px solid rgba(245,130,13,0.3);
            border-radius: 20px; padding: 6px 16px;
            font-size: 12px; font-weight: 600; letter-spacing: 1px;
            text-transform: uppercase; color: var(--orange);
            margin-bottom: 24px;
        }
        .hero-label::before { content: '●'; font-size: 8px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }

        h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(42px, 5vw, 68px);
            font-weight: 700; line-height: 1.05;
            margin-bottom: 20px;
        }
        h1 .highlight {
            background: linear-gradient(90deg, var(--blue-light), var(--orange));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 17px; color: var(--text-muted);
            line-height: 1.7; margin-bottom: 36px; max-width: 480px;
        }
        .hero-btns { display: flex; gap: 14px; flex-wrap: wrap; }
        .btn-hero-primary {
            padding: 14px 32px; border-radius: 8px;
            background: linear-gradient(135deg, var(--blue-mid), var(--blue-light));
            color: white; font-size: 15px; font-weight: 600;
            border: none; cursor: pointer; transition: all 0.2s;
            box-shadow: 0 0 30px rgba(45,91,227,0.4);
            text-decoration: none;
        }
        .btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 0 40px rgba(45,91,227,0.6); }
        .btn-hero-secondary {
            padding: 14px 32px; border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            color: white; font-size: 15px; font-weight: 500;
            background: transparent; cursor: pointer; transition: all 0.2s;
            text-decoration: none;
        }
        .btn-hero-secondary:hover { background: rgba(255,255,255,0.05); }

        .hero-stats {
            display: flex; gap: 32px; margin-top: 48px;
        }
        .stat { }
        .stat-num {
            font-family: 'Rajdhani', sans-serif;
            font-size: 32px; font-weight: 700; color: var(--white);
        }
        .stat-num span { color: var(--orange); }
        .stat-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

        /* HERO VISUAL */
        .hero-visual {
            position: relative;
        }
        .hero-card-stack {
            position: relative; height: 420px;
        }
        .mock-card {
            position: absolute;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 24px;
            backdrop-filter: blur(10px);
        }
        .mock-card:nth-child(1) {
            width: 300px; top: 30px; left: 50%; transform: translateX(-50%);
            background: rgba(26,58,143,0.5);
            border-color: rgba(61,122,245,0.3);
            box-shadow: 0 20px 60px rgba(45,91,227,0.3);
            animation: float1 5s ease-in-out infinite;
        }
        .mock-card:nth-child(2) {
            width: 260px; bottom: 20px; left: 10%;
            background: rgba(15,32,87,0.7);
            animation: float2 6s ease-in-out infinite;
        }
        .mock-card:nth-child(3) {
            width: 200px; top: 10px; right: 5%;
            background: rgba(245,130,13,0.12);
            border-color: rgba(245,130,13,0.25);
            animation: float3 4.5s ease-in-out infinite;
        }
        @keyframes float1 { 0%,100%{transform:translateX(-50%) translateY(0)} 50%{transform:translateX(-50%) translateY(-12px)} }
        @keyframes float2 { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
        @keyframes float3 { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }

        .mock-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--blue-mid), var(--orange));
            margin-bottom: 12px;
        }
        .mock-line { height: 10px; border-radius: 5px; background: rgba(255,255,255,0.15); margin-bottom: 8px; }
        .mock-price { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: var(--orange); margin: 12px 0; }
        .mock-tag {
            display: inline-block; padding: 4px 10px; border-radius: 4px;
            background: rgba(45,91,227,0.3); color: var(--blue-light);
            font-size: 11px; font-weight: 600;
        }
        .mock-verified { color: #4ade80; font-size: 12px; font-weight: 600; }

        /* SECTION */
        section.main-section {
            position: relative; z-index: 1;
            max-width: 1280px; margin: 0 auto; padding: 0 40px 80px;
        }
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 32px;
        }
        .section-title {
            font-family: 'Rajdhani', sans-serif;
            font-size: 32px; font-weight: 700;
        }
        .section-title span { color: var(--orange); }
        .section-link {
            color: var(--blue-light); font-size: 14px; font-weight: 500;
            text-decoration: none; display: flex; align-items: center; gap: 4px;
        }

        /* FILTER BAR */
        .filter-bar {
            display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 28px;
        }
        .filter-chip {
            padding: 8px 18px; border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.12);
            background: transparent; color: var(--text-muted);
            font-size: 13px; cursor: pointer; transition: all 0.2s;
        }
        .filter-chip:hover, .filter-chip.active {
            border-color: var(--orange); color: var(--orange);
            background: rgba(245,130,13,0.08);
        }

        /* LOCATION SELECT */
        .location-select {
            padding: 8px 18px; border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.05); color: var(--white);
            font-size: 13px; cursor: pointer; outline: none;
        }

        /* SERVICES GRID */
        .grid-services {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px; overflow: hidden;
            transition: all 0.3s; cursor: pointer;
            position: relative;
        }
        .card:hover {
            border-color: rgba(61,122,245,0.4);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .card.featured {
            border-color: rgba(245,130,13,0.4);
            box-shadow: 0 0 0 1px rgba(245,130,13,0.2);
        }
        .card-img {
            width: 100%; height: 180px;
            background: linear-gradient(135deg, rgba(26,58,143,0.6), rgba(245,130,13,0.2));
            display: flex; align-items: center; justify-content: center;
            font-size: 48px;
        }
        .card-body { padding: 18px; }
        .card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .tag-featured {
            padding: 3px 10px; border-radius: 4px;
            background: var(--orange); color: white;
            font-size: 10px; font-weight: 700; letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .tag-new {
            padding: 3px 10px; border-radius: 4px;
            background: rgba(74,222,128,0.15); color: #4ade80;
            font-size: 10px; font-weight: 700; letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .card-price {
            font-family: 'Rajdhani', sans-serif;
            font-size: 28px; font-weight: 700; color: var(--white);
        }
        .card-price sup { font-size: 16px; color: var(--text-muted); }
        .card-title { font-size: 14px; color: var(--off-white); margin: 8px 0 6px; line-height: 1.5; }
        .card-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; }
        .card-location { font-size: 12px; color: var(--text-muted); }
        .card-verified { font-size: 12px; color: #4ade80; font-weight: 600; }
        .btn-call {
            display: block; width: 100%; margin-top: 14px;
            padding: 10px; border-radius: 7px; border: none;
            background: rgba(45,91,227,0.2); color: var(--blue-light);
            font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s;
            border: 1px solid rgba(45,91,227,0.3);
        }
        .btn-call:hover { background: var(--blue-mid); color: white; }

        /* CATEGORIES */
        .categories {
            position: relative; z-index: 1;
            max-width: 1280px; margin: 0 auto; padding: 0 40px 80px;
        }
        .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
        .cat-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 24px 16px; text-align: center;
            cursor: pointer; transition: all 0.2s;
        }
        .cat-item:hover {
            border-color: var(--orange); background: rgba(245,130,13,0.07);
            transform: translateY(-3px);
        }
        .cat-emoji { font-size: 32px; margin-bottom: 10px; }
        .cat-name { font-size: 13px; font-weight: 500; color: var(--off-white); }
        .cat-count { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

        /* CTA STRIP */
        .cta-strip {
            position: relative; z-index: 1;
            max-width: 1280px; margin: 0 auto 80px; padding: 0 40px;
        }
        .cta-inner {
            background: linear-gradient(135deg, var(--blue) 0%, var(--blue-mid) 100%);
            border-radius: 20px; padding: 60px;
            display: flex; align-items: center; justify-content: space-between; gap: 40px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden; position: relative;
        }
        .cta-inner::before {
            content: ''; position: absolute; top: -40px; right: -40px;
            width: 220px; height: 220px; border-radius: 50%;
            background: rgba(245,130,13,0.15); pointer-events: none;
        }
        .cta-text h2 { font-family: 'Rajdhani', sans-serif; font-size: 38px; font-weight: 700; }
        .cta-text p { color: rgba(255,255,255,0.7); margin-top: 10px; font-size: 16px; }
        .cta-btns { display: flex; gap: 12px; flex-shrink: 0; }

        /* FOOTER */
        footer {
            position: relative; z-index: 1;
            border-top: 1px solid rgba(255,255,255,0.07);
            padding: 40px;
            max-width: 1280px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 16px;
        }
        footer .logo-text { font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; }
        footer p { color: var(--text-muted); font-size: 13px; }

        /* MODAL */
        .modal {
            display: none; position: fixed; inset: 0; z-index: 200;
            background: rgba(10,20,60,0.8); backdrop-filter: blur(8px);
            align-items: center; justify-content: center;
        }
        .modal.open { display: flex; }
        .modal-box {
            background: #0d1e4a;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; padding: 40px;
            width: 100%; max-width: 480px;
            position: relative; animation: slideUp 0.3s ease;
        }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .modal-close {
            position: absolute; top: 16px; right: 20px;
            background: none; border: none; color: var(--text-muted);
            font-size: 22px; cursor: pointer;
        }
        .modal-box h2 { font-family: 'Rajdhani', sans-serif; font-size: 26px; margin-bottom: 8px; }
        .modal-box p { color: var(--text-muted); font-size: 14px; margin-bottom: 28px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: rgba(255,255,255,0.7); }
        .form-group input, .form-group select {
            width: 100%; padding: 12px 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px; color: white; font-size: 14px; outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--blue-light);
        }
        .form-group input[type="file"] { padding: 10px 14px; }
        .form-submit {
            width: 100%; padding: 13px;
            background: var(--orange); border: none; border-radius: 8px;
            color: white; font-size: 15px; font-weight: 600; cursor: pointer;
            transition: all 0.2s; margin-top: 8px;
            box-shadow: 0 0 20px var(--orange-glow);
        }
        .form-submit:hover { background: var(--orange-dark); }

        @media (max-width: 900px) {
            .hero { grid-template-columns: 1fr; padding: 60px 20px; }
            .hero-visual { display: none; }
            header { padding: 0 20px; }
            .nav-inner { flex-wrap: wrap; height: auto; padding: 14px 0; gap: 14px; }
            .nav-search { max-width: 100%; order: 3; }
            nav { width: 100%; justify-content: center; gap: 10px; order: 4; margin-top: 5px; }
            .btn-outline, .btn-cta { flex: 1; text-align: center; padding: 12px 0; }
            section.main-section, .categories, .cta-strip { padding-left: 20px; padding-right: 20px; }
            .cta-inner { flex-direction: column; padding: 40px 24px; text-align: center; }
            .cta-btns { flex-direction: column; width: 100%; }
            .cta-btns a { width: 100%; text-align: center; }
            .modal-box { padding: 24px 16px; margin: 10px; width: 100%; max-height: 90vh; overflow-y: auto; }
            .hero-stats { flex-direction: column; gap: 16px; align-items: center; text-align: center; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="nav-inner">
        <a href="home.html" class="logo-wrap">
            <div class="logo-icon" style="background:white;padding:2px;"><img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAQDAwQDAwQEAwQFBAQFBgoHBgYGBg0JCggKDw0QEA8NDw4RExgUERIXEg4PFRwVFxkZGxsbEBQdHx0aHxgaGxr/2wBDAQQFBQYFBgwHBwwaEQ8RGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhoaGhr/wgARCAQABAADASIAAhEBAxEB/8QAHAABAQACAwEBAAAAAAAAAAAAAAEGBwQFCAID/8QAGwEBAQADAQEBAAAAAAAAAAAAAAEDBAUGAgf/2gAMAwEAAhADEAAAAd/xUSiWUEFAAAAAAQWUSgBFEolCUBCgRQBKAAACURQgsoASiVBQlAlCUAAiiUIsKCUBCkKQUAACUAlAAAABKAAAAEoSwoAEoAAAAAAAAlAAAAAABKAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAJQAAAAAAAAAAAAAAAAASgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQoAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAJQAAAAAAAAAAAAAJSLCgAAAAAAAAAAAAAEKQoAAEoAAAlAAAAQoAABCpQgsCoKgqCoKQpCgAAAAAAAAEKgoABCpQAAAAAAlAEoEKAAAAQoAAAAAAJQEKACUAABCgAIKlAAAAEoAAlBKWWEoUEIKlAAAAAAAAAABCkKFBCUAAAAJQCUAJZQAAAFSgQqEqCgSgQpCpSVCgAQAWgBJZVSgCUAJZQAEAAAAAAJRFBFssAAACwqCoSylBABCoKlUgpEsCoKlUEJQAABKAAJQAAAhQBKJQilllQCLFLAVJQJQAAACKACFWUBIVQQAAAFBAAAAAJQSgRQBQgKSKUEEVRIUlAAAFgSyllEAAAAAAAAAAAAAAlAACAWUAAAEAUUISkKAABKEpUoigEAAlBKAAAJQAAJQFBCUAgUCpUSgCUJQAAEBVBACURQCUAAAABCgAAAAAAAASiLFqEoAEAFAAWVAAAAAAAIoAAAAAAASwoACUJQAAACWVYEqUAAAAAhVikIKBLBQAAAAAAAAAAlBKAAAACAUhQBLACgllIsLKAAAAAAAAAAAAAUAAAEBUogFgqCpUAAllAEoAAAEFAAAAAAAAAAAlEoAAAEWhAAAAEolAQoIoAAEKlIsKAAQqVQQAQqFoAAQFEKAAAQoAARBQFgqCpUhVJQEAEKFBCABYWoSgAAlAABKCUSgAAAAAABKCUAAiwUUEAAhSUAACBRQQAAFAAAAgFAABKJQABAWKIoiwAFJQixKlACVSVAJULLBRQQlEsKhaEAASwqUJSUAAAAAEoAAABQQAQqUlAQqUAAAAABQAAAAJQAAAlAAAAAAEShKWAApEoAAAEolBAAVCgEVZQEAAAASwVCpQAAAQoEsLLCgShKAJZQQoAAAAAEoABQAAAAAACUAAAAAAAAAABAIAUllAEsFlABCyhLACgSxaRKAAAACWUixVEAAAEKAACWVUpAAAJRQQACWUAAABQAAAD8sV+8eXtRY3u6O++t8//Ozqbz/DSbJh3bydGfVb+7Lzf94svpKaFyrT3touj7nm9P7GX4AAAAAAlEELBVlSKWVCoRQEKAQssLBVlQQpCgAAASglBCpQAAlBCywWBUUoJUASwUUEARRAUWWEoAIpUoAPy1tlw7B13rf8Oxxudwe2zrY1dYdjuTr9bYw3u+L1Rl/Iwn9Pj7zPj4rDuukva5MWF8vZHbc3o6752TY5+dfoGTZPqf8AXd1NssUyn0nC+xs4QAEsKAAESxRRApEpCwUVAAIFAssAQUAAAAAhVSgEAlAAACKAAAAAAACBZSKJZSKUESlAAdJ0mld/n95j33s3q8rBNq8nWuptbLwXvNg6+xqDKdhzFl6HuP3au2sfP1fmo/HqO+ffxgONbjbWppvOO/wLLgyXDek2Pwe1g/bdxi3kPV7P5+pc+9Hw+8HZ5iUAAAAgAhZYFgWKAsFggKAsFCShKAAAACUSgAAAAAAAAAAAAAABFgspAoCygJKKA17+2k+lzfnncrc+7pfjiOP7L09vENq86ae5PqMGwA4PO1Zm1/3/AC1n9dzgbK5WrPpPR/K13sThehgxZ0Dhaz21c+rqvNuv15savbfln+FfnnvczyPUOf8AW5vfjvcgAAAAEAAQUCkSoAUCoLFQlBCgAAAASiFACUAAEKAAAQoAAAJQASxQAAKAEBWLd75u29Thdn1m/Opzf31O3Lpbn5d7XN36xfrsuPOE+sWaJin1jy3z932BdjjPv4vR5f6Pj6vzle8PM2web1dsMYyXj9r6lfORw/z11yt/ZnV9RmW3q6N2z96q7nE7njbB13+b/oGzO11Vs70PF/cdXngAAAgACWFgoAqQKACKCWFQWKoIAlhQASoUgsqggACUsogBBUoAAAIApYAWABSFCDGbNd64Zd3+Nm+I5hwdHby3Kvn6529cNuid7S+fn5vY5GT7A0zcGbI8fXY1X38Pr4/S/fZR1f12fBfPxfi/eO990myMGxnf6flrfwnvP24dnjvUfttbUuX9jlZnjOS/PsfL6dzjH+yzauMZV1fV/nfvtwus7L3PkaPv5AAELFSWUSwoBFWAAVJQJQhbKQAAlAAAAEoAAEKARVEELKJQAELKJZSABQABRKAR5/2/5q6Gl+vobWfdZvjFvQGI5npbUGrm8w8HP9fej4P3fm5cNSH6dhlu4dLa1pmuROZ0/wA/s19h8/Qx/DNqTY1tc5d8a64nYfjHjvW/V+b8vr9fwX523++F5r7rxv5aR3niPX5Paa87n78z6L62Fp7aeLLzh6DjAAJRKIACoACwALBZYAUAJChBQAAAJQAAAAlLKAIAAAAAABFgWCwWWKogCWLpfV/afPc5e9NWbD4Ols7Qpobcn1E6rzR6d8xdPn/D4++nzvvcHC21y+j8389P6O3sPGdQZT1eXneVdN2ehvd70n5aGyY9tZ1h2bYM+IcLMOZxuxqrrsoxby3pqjV2PpMnzYMe2xrbIery8wV6rzukdn432W1oYdl+K8n87/QNqj3XjgAAAQARalEsQAoSgBKCUlhVhKAAAAAAAlAAACUAAiglAAAAECywFICgBXSd3rr7+NE7I1tvfraWEbk0R6N0ti2XS2BK1JqTuem7/G+smxff+P6zT9PjGeL1dd4FyeL6LgX0J5+2pyentG/P1pb2sNaZrgnoPOehLoX9dXayrcmr880d7XPCr82/SJfl8Xt9pYr0vtPG7F+fpv6aFdVrTb2j97mZjj2c4P8Ann6Btjl4/kHrvLhnxAAhKAJQAlgUEFAlEKShAtCEoSgAAAAEsoAAAlKCAASipSAAAJYLKAJQACtNbl0Js4de+j/Nvo/bw6y35p3cepmX50vi+txa/wBJcfo6n6X4+t/T7P1FozevH6GrdR+pfPWfH1Oc9httNL99kfM8V67qeoyDEe9w9a/X67j9L5rXeRbYcvqcfAsr1b5P1v6Pm+d9FeXxdi7mjy/P+W4V+pflu58x80ftjyemWrNpcvqzS+6dW59fONb7D114712a5jgOfdLQDp88AEAEWgBACUAAAABYoBAABCgAAAlAACKWKQAARSgRKAAlEpZRAAAUB539EedNvDhfofz16E2cPRbW1ZtPS2J5P9Y+TtnBw78fXT07flfncO29VbW4fT+eNymtllktdVqjMsnxinZ9W8F+gbEyPS+x/Q+ayL8v013u6XT9f9Tx3tZ9fPZydt2+Ta2914LVz5vrfI37/Op2no3zd6Q4/YuuNj661trINfZ/gXmPSZDsTXGx8+KU6/LAEFlCUAlESxalARKBBYVRAAEsKAABKJQEKlAAAAAAIsFRSkAAigCFEolAAFeevQuitrFrX0Z5y9H7WLFdt6N3jp5p5O9Zef8ALiwInW0rA27uHz/6A42/Ti6ub9NHcHC+npfrvzQXoefWDdhk+WeN9l03c/GuOjyeVi35/XmfXfadxjv47N+uX6nyE1NtnRXb4WI38/vvcH6s+r88/wBIaX3RxO5dabK1Fiy5zr7YevfK+pyPYeCZ3t6gdXmgShKIACggAAACUSygAAEoAAAAEolAAAAAAEoAAqKhKAAJQAACgANSbbwTN8eefRvm/dvQ18J9F+bfSOpl+xqZul11t/4yfHkG9r1Xd5/O9W+RfQOjnz3V20cV0Nnn6i5HY7WGbg+ev0tns+mw/F+L6DtOr+nD9H8ubn+fUx/P8ex/q8LY0/P9exxPz8y7h0l1+R9X473f0Ni5t2Tz/fsrFn+NHbw0bvc/amuM2wjwvus8y7ou99B58NzVELKQABLFKQQoAACBYFlJQAAAAAAllAAAAAAABBULKEpSVAAACUABQAHF5UTXf1n/AAfprrOb9/GbunE5DH9xHzqHT/rnj7uHy/sPbXAxZ8gYP0nI62zse1x+Oj1MmxucnndfjfWR5Tn1NfZpl331eB+eA7D6nNq9RycU/TmdbIsg/HT/AHvPY10/z9eo8w27rP0hob3Jscjr2yxjmHflmm/z8X6P67b87/R9l/se18QFAAkqFlgWLQggqFAQCkoAASglAAACWFikoAAAACFABALC0JKAAhUoiqCEqgAJYYZie0vKW9g9Pc3znuLDl5/V9btDn9HV3D27NPc0zNnfUy6ru1Pwl13++Zdflw9d2vRZpn1Xd/ozaQfeMlqL8x8fHTaX2MGRa0+Prtcr7+vi5cPa+k/LW3dDc2fLOR1rx/31lkw4xsvruh1N/rs/wbb3D737xfR+YAEKlEVEVYCoKhLBSwAqUJQlAQAAAAAAAQoABCgAAgUAsKEASiBQKEJSUUABLC6S3Z12X48nb+0Vyupr956AxzWepk9BWXRz+bMQ9SebOvp9ZLdrH8/fyT6/X8l+dg53oP61snqPsfJnzh+/WnVeYqm9sGwP62MP1fm7OD9L8fdx36+Sfea496F09nt0cbrddpPsNib2j8a85Pz4T9Hy7O+LyvSeQlNnVAQCwssKBAAAqUESyiBRQEASlABAAAIoAAAAAAASlShAUQACAWUlAAAFAILA1PpL2F5y6Gv3HfaX3vk+eJtfQ+Za2TYnQZA1MvlLq/VnUdLX1Ts7NWpl0Br7191mfH5Tu7NKb+vEuXHb8k+3I3Pi+tK7F3B2WhsYdrPfzDk8md1vHuNvW4/bWczfusuXi25q5D+nb4D5z1fzsLoNmYstHc80EJZVJCy1LKICypCrFgBUFBFgsoCASiggAACWFAIKAAAAACUAAAJQSkUAAAAAoACUTruyieVuq9P+bOvq7y1/r3e2P6/HaOlePg+96ONydPMABPOXo7V+zi0c+b2NH6fP1Wf+gNd7E4u9Ua2VYKnGs5Wtcf7Dd1un2L8684PpPrsuLtfldnkck9L40LAgAAQoIqopJQAhSWUASiUCUSiWVQQAABLCpQAlAAAAAAEoAlFBAAAAJQBQAAAEomPZFLPJ/VesPP3U1srzDzxkhztp8jEcH3uN5u2Ti+tkXj/vrZb+P60wX8s/ZPjXvJzkfH3Li+4vGTk/Ou9e7WLZes8pzi3hcToOv8z66czsNkYc/wCHZnoPIh9fMoEoAAAAIUIgAKAEoCFAAAAAAAAASkWCgAAlEUAACAKqJQASoqiAoAhZQAAIWAUT8/1Gn9Q+vug3cPlnOvvAd3D6M6rROS4frufvI8t+PrG8hnQYvrNObqv8Y2zw9X8hMyxvl96usZuDrNfb6fK8K6njdzIcb/TIuV28bzjJey63A+P0OrwggKIKAAACWUlAAEgVRJZQABFCVREoAIoAAAASglBCgAAAASlESgJSLCgABQAAABCgAAAij5x/IVmkde+sfjbxeP56XwvZxaf7zv8ApM3x2vMwv4l3h+HS/r+b/o378HsObbj3xnPd7GrrXJ88/To8nrOyrpceU+vkICiUlAAABKAJQABJQJQlEoEVZQRCwBaEAAAAASiWCgAAAAAIKAQpCpQAAFlAAAAAAAAgoAAIoiiLAoAAACAAAoAAAAAAAACUAQQVCgSiWVUsSywAoAAAEoAEKlAAEoAAAAAgUAsQFsCvnV/38bJx7zhxt/X9Ld55Pp6+aN3jo7HF/HT2sdvD6veUH38er75Slet/31rsbn7PD+fKM6Gt6vvk+p6++/JWxsOTd3XcrS+vk27z/IW/M+PYqXT2UuG/XzkH15P27ua+2fw1ppmX13++AZ/q5w+PoI/L8+j13yuxuBp6au5uH99L7b3efz7OL0eZy+Nr/HuN3NqftqZrbO6LqbO+ny++S9Hmcf5wXGuH3tvtQsOXcH76Z5X38bdYplXZ41GbCACAAAJYAUCWFBKAAAAhUoAAAAAAAAIWCgLBYAAHT+VPS3mnp6nfbq1Z6Fx3FcU261s2udjmP60fq7Z+r+xo7j7zt8t5u1r1sKfH10nb/fxi+/IXedLk3oObtP8APZLi73nXCfXPkvo621tj6E9MaebyDlfIw3p6vr9jGT8Lozzzuvy1va37eqdI+gJdT6X3TpfawegNgYBn/M3AxZAOB1OSTBnxrFNo685nWxvbWpNta+xydXZfrs/TJP2z0xvhZg63G07+ezdZeb9RtDuNZ7L9D5rXWNZJjfm/UbE5nL5/p/J45ge3dYcvrdTt/Um3T9x6DzwAAIAAAIUAAAAhQAAAAEoAAAAAAlLAgKAAAB+Pmv01+WfF5D/fe2E9HV6rPNUdV9PWvJ8wemuZt6S1ftPV3T0+z5WU9lGDc7L+X8fW2J9/PJ3fIOTYzyPQcz1w8zfPN298eZPv43dfLvR+D5xzNzCfO/r3y5tYcr3z5D9QY/rV+qef3O9rbsy44nR1Tpjc2mevo+gM+wHPOZt8WeT25g9Y8ryN6fwZO8S6udrzYmuuZ1cY25qXbfP6OI4jl2I6W/tDuej731Hkg2NdqLbmoeJ3vx3Lp3cXx9a8xvJMa5fX7GZB+mxq4n+GX47r7OS5rp3Z3Y4fajtcUAAEAAABQQAAAAAAQqUAASgCUAAAAAIsLFWURLAodT8eW9nD68aAyddq+cct1dtYuJ6i83ep/i6Z1bs/V+3g9MZToHstDY3Y0jfj63d8YLnWDL5E5nDyPvc3I+p9F/jyt3yNtnCOp6Or60dP3PD6E1Ptjhffz5MyHqPw73Ob20v6q0s/7F5e7qbS+6NMdjQ9AZ9gGwOXt4WzQuFZZyfx+Pr8OZqXm8bu7O1zzcU19j427q3bf1j6HWm6tb2/nsnTHZ6u1teYHxerye9wL55HnvRd9sTr+w9P5TXOM5LjPmfWbd5+B8v0Hmsxxrg43g2OszbCdh8zp5QPV+RlKBAAAIoBQQAlUEAAAASgAAAAAAAAABKAUhChKOv1Fu1l+PLPTevvy2sPkrLPRllxzJF0tjTmuPVU2sHlj9PUVyfPlx6kS6i2z9NTN5byH0HdnFLWlsY15+9SzZw6K3tXx9Sy4cmmdf8AqabuvqTbc+tbKS/H3rPUnqebWDB84rXypXx9RVYjh23nM62mOXtn61tno+8rscSfP3fvHhuKbd+eb1dLcvbt1dvAMw7B0uXLG3pYH0O27yuxqSbbYsuo+XtFZieV29LlSmfCikWJUoBKAgqFAAAAAAAAQUhQAAAAAAAJYLBYpLKAAQKroU75i3fryoRUtRUHD/KuwUS9J3RYCgRFdF3tCF+WMJlFhVlBCkK+cdMkBFEqFnGxiTMLiuUV9SlkY0mTOi74ElsqwgLFApCwFlAQACUAAAAAAAAAAJQAAAAAAIKhQFCAWUJUgW4NnH5HF5WP9J8ud+vG5dcn8Pzysxzueg4py+f+vTR8cjk99WOdj0P1K5GT4hZypyenM2fH6GH5jh2YHU9Jyfiu3w/uuDHK7TuMAjnffK59fpi/O5h+/Ucz8Ts+l+vk7Dj9T3Ryuq7LgV23b4tlUYRmeH5ifhj2RY3XA5v55dHEx/8AX4r9P34X6R8MpxGO96vhdnX65BjWS0UAQAAoACAQqghKAAJQBKCUSgIKAAAAAAhUqxSRYUKikKEpAMYycYlkXLGKfrkw6jt7D8egyWRxfz56sX+Mrh0vafvTE+d3qMc4uWq+foML/XLyY7+eTF6jq8rGJ5DyxjP4ZbDifj2Njp+Fktrpp3I6brMrR1fTZaro++g/DF8vGKZT9E6Xt/svS3uSdN99uX8+l71Jwsey4vTdyUqFBAAAUIAQCqCJQiiKoIASiUEoBKAAAAAEUoIABCkCrKgBBQSiwAAAAAAACoWAAAAAAAAAAKAgKlCUEoSpFKgWAAWFgAVBZYlligUIAAAABLKAQpAUABKAAAASoUAAEWCygAABBUoCkAAFSksFIWAoJYAWWFgLBUCwAAVBSFACAoEWACwAVBUACiRSwoQLKgBKAoIAAABLKAAAACFAAAABKACWCoUhQoJChKEqpSRQQVCrKRQIVKIAAApLKSoFhUAFQAAFgBZYVBYACwAAAALBQkCrKAkWLUqAASgAAAlAAAEoAAAICyqCACAqwFQlQWCgLCVCrIUAACwAAAAARQAAAIVBQWABYhQAAEFShKAARQABYACwWUiWCwtipLKAAAEoAAAAAAlCKsUkooIAlACUQKgVKSgABFAAAAAAAAAAAAAgoSgAAAAAAlgoSgAKRSSwtCCFlhKqxYlABFAAAAABKAAAAAAAAAAAAASwVKJRFEAC2AAsAAAAAABYAAAAAAAAAASgAAAAAAsqIoCpYlAIWKSgSkqFAlAAAAAEoAAAAAAAJRKAAAEoARYWAUsWICgCkn1AAAAAsAQsWKAAFgS0gAACwlAAAAAsCkJSBSkixaEllJQIBSKAAAAAAAAAAAAAJQAAAIKABKJQlFlEAAIUAUBAJYKlUEAEKlJQAAEKlEolQpAAoAAEKhaAEAIKlAABCyiKBAoIUsKEiwoCUAAAllAAAAEUAAJSUUEAShLFKQABLAFpBRACUillEASliwoQAAgWVQQBLCywoACUJSKUESgACBQKEEKFgBUighRQRKBLFsolEAAAAAAAAAAAAAAAAAAAJSVCgAAASgACAFIFAAAKEsFgWAUSiLEFWKEChAAIUQAUsBUAEKAARVlQAlAAAAEoASwoAAAAAAAAJQAAAEFgqUSgAQoAAABApYoEAQpUoiiAAWUEKBAWUlgWUlEEKAABLBYLKEoASwqUAEKCWUAAlAAAAlEoAAAAAAAAAAAAAJRAsCgSgABKAAAEoAAEKlBFsUlAAQVEoCUAQKAAAQoAAAAAAAJQAAAAAAAAAAASgAAAAAAAAAlAAEolQqVQQABKJQAASiBaEAShLAsWpQEARVlEAAlAAAABKWUAQACUUEAAlgoCUAAigAACUAVKQAQUAAAAJQAAAlAAABKCUAAASgAABKAUlARKIsUUiwssFRAWpUAAiiUAAAEoAAAABREoAJUKlUEASiKAAAAAAAAAABCgAAAAAEKAABKJYKAQqCpQQWCwKAAQqUQKAlAJQSgACUCUAIKgpCgEFgqUSgAAAAABFAABCgAAAAAAAAAAAAAAAAAAAAAAAAAAAAiiKAAAIoigAAAAAAAAAAAAAACK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" style="width:44px;height:44px;object-fit:contain;border-radius:10px;background:white;padding:3px;" alt="Servi-Job Logo"></div>
            <span class="logo-text">SERVI-<span>JOB</span></span>
        </a>
        <div class="nav-search">
            <input type="text" id="searchInput" placeholder="Buscar servicios, emprendimientos...">
            <button>🔍</button>
        </div>
        <nav>
            <a href="login.php" class="btn-outline">Iniciar Sesión</a>
            <a href="register.php" class="btn-cta">Registrarse</a>
        </nav>
    </div>
</header>

<!-- HERO -->
<div class="hero">
    <div class="hero-content">
        <div class="hero-label">🔵 Plataforma activa en Caracas</div>
        <h1>Conecta con los mejores <span class="highlight">servicios locales</span></h1>
        <p>Encuentra emprendimientos verificados, profesionales independientes y servicios en tu municipio. Rápido, seguro y confiable.</p>
        <div class="hero-btns">
            <a href="#servicios" class="btn-hero-primary">Explorar Servicios</a>
            <a href="register.php" class="btn-hero-secondary">Publicar mi Servicio →</a>
        </div>
        <div class="hero-stats">
            <div class="stat">
                <div class="stat-num">1.2<span>K+</span></div>
                <div class="stat-label">Servicios activos</div>
            </div>
            <div class="stat">
                <div class="stat-num">430<span>+</span></div>
                <div class="stat-label">Verificados</div>
            </div>
            <div class="stat">
                <div class="stat-num">5<span></span></div>
                <div class="stat-label">Municipios</div>
            </div>
        </div>
    </div>

    <div class="hero-visual">
        <div class="hero-card-stack">
            <div class="mock-card">
                <div class="mock-avatar"></div>
                <div class="mock-price">$45 <span style="font-size:14px;color:rgba(255,255,255,0.4)">/hora</span></div>
                <div class="mock-line" style="width:80%"></div>
                <div class="mock-line" style="width:60%"></div>
                <div style="margin-top:12px;display:flex;gap:8px">
                    <span class="mock-tag">Plomería</span>
                    <span class="mock-verified">✔ Verificado</span>
                </div>
            </div>
            <div class="mock-card">
                <div class="mock-line" style="width:70%"></div>
                <div class="mock-price">$120</div>
                <div class="mock-line" style="width:50%"></div>
                <span class="mock-tag">Electricidad</span>
            </div>
            <div class="mock-card">
                <div style="font-size:22px;margin-bottom:8px">📈</div>
                <div style="font-family:'Rajdhani',sans-serif;font-size:18px;font-weight:700">+28%</div>
                <div style="font-size:11px;color:rgba(255,255,255,0.4)">Este mes</div>
            </div>
        </div>
    </div>
</div>

<!-- CATEGORIAS -->
<div class="categories">
    <div class="section-header">
        <h2 class="section-title">Categorías <span>Populares</span></h2>
    </div>
    <div class="cat-grid">
        <div class="cat-item"><div class="cat-emoji">🔧</div><div class="cat-name">Plomería</div><div class="cat-count">142 servicios</div></div>
        <div class="cat-item"><div class="cat-emoji">⚡</div><div class="cat-name">Electricidad</div><div class="cat-count">98 servicios</div></div>
        <div class="cat-item"><div class="cat-emoji">🍕</div><div class="cat-name">Comida</div><div class="cat-count">231 servicios</div></div>
        <div class="cat-item"><div class="cat-emoji">🏠</div><div class="cat-name">Remodelación</div><div class="cat-count">87 servicios</div></div>
        <div class="cat-item"><div class="cat-emoji">💈</div><div class="cat-name">Belleza</div><div class="cat-count">176 servicios</div></div>
        <div class="cat-item"><div class="cat-emoji">📦</div><div class="cat-name">Delivery</div><div class="cat-count">65 servicios</div></div>
        <div class="cat-item"><div class="cat-emoji">💻</div><div class="cat-name">Tecnología</div><div class="cat-count">54 servicios</div></div>
        <div class="cat-item"><div class="cat-emoji">🌱</div><div class="cat-name">Jardinería</div><div class="cat-count">43 servicios</div></div>
    </div>
</div>

<!-- SERVICIOS -->
<section class="main-section" id="servicios">
    <div class="section-header">
        <h2 class="section-title">Servicios <span>Destacados</span></h2>
        <a href="login.php" class="section-link">Ver todos →</a>
    </div>

    <div class="filter-bar">
        <button class="filter-chip active" onclick="filtrar(this,'todos')">Todos</button>
        <button class="filter-chip" onclick="filtrar(this,'chacao')">Chacao</button>
        <button class="filter-chip" onclick="filtrar(this,'baruta')">Baruta</button>
        <button class="filter-chip" onclick="filtrar(this,'sucre')">Sucre</button>
        <button class="filter-chip" onclick="filtrar(this,'libertador')">Libertador</button>
    </div>

    <div class="grid-services" id="servicesGrid">
        <!-- Demo cards (en prod vienen de PHP/logic.php) -->
        <div class="card featured" data-municipio="chacao">
            <div class="card-img">🔧</div>
            <div class="card-body">
                <div class="card-top">
                    <span class="tag-featured">Destacado</span>
                    <span class="card-verified">✔ Verificado</span>
                </div>
                <div class="card-price"><sup>$</sup>45</div>
                <div class="card-title">Servicio de Plomería Express — Reparaciones urgentes a domicilio</div>
                <div class="card-meta">
                    <span class="card-location">📍 Chacao</span>
                </div>
                <button class="btn-call" onclick="window.location='login.php'">📞 Ver Contacto</button>
            </div>
        </div>
        <div class="card" data-municipio="baruta">
            <div class="card-img">⚡</div>
            <div class="card-body">
                <div class="card-top">
                    <span class="tag-new">Nuevo</span>
                </div>
                <div class="card-price"><sup>$</sup>80</div>
                <div class="card-title">Electricista Certificado — Instalaciones y mantenimiento</div>
                <div class="card-meta">
                    <span class="card-location">📍 Baruta</span>
                </div>
                <button class="btn-call" onclick="window.location='login.php'">📞 Ver Contacto</button>
            </div>
        </div>
        <div class="card featured" data-municipio="sucre">
            <div class="card-img">🍕</div>
            <div class="card-body">
                <div class="card-top">
                    <span class="tag-featured">Destacado</span>
                    <span class="card-verified">✔ Verificado</span>
                </div>
                <div class="card-price"><sup>$</sup>12</div>
                <div class="card-title">Cocina Doña Carmen — Almuerzos caseros con delivery</div>
                <div class="card-meta">
                    <span class="card-location">📍 Sucre</span>
                </div>
                <button class="btn-call" onclick="window.location='login.php'">📞 Ver Contacto</button>
            </div>
        </div>
        <div class="card" data-municipio="libertador">
            <div class="card-img">💈</div>
            <div class="card-body">
                <div class="card-top"></div>
                <div class="card-price"><sup>$</sup>15</div>
                <div class="card-title">Barbería Estilo Libre — Cortes modernos y afeitado</div>
                <div class="card-meta">
                    <span class="card-location">📍 Libertador</span>
                </div>
                <button class="btn-call" onclick="window.location='login.php'">📞 Ver Contacto</button>
            </div>
        </div>
        <div class="card" data-municipio="chacao">
            <div class="card-img">💻</div>
            <div class="card-body">
                <div class="card-top">
                    <span class="tag-new">Nuevo</span>
                </div>
                <div class="card-price"><sup>$</sup>30</div>
                <div class="card-title">Soporte Técnico PC — Reparación y mantenimiento de equipos</div>
                <div class="card-meta">
                    <span class="card-location">📍 Chacao</span>
                </div>
                <button class="btn-call" onclick="window.location='login.php'">📞 Ver Contacto</button>
            </div>
        </div>
        <div class="card" data-municipio="baruta">
            <div class="card-img">🏠</div>
            <div class="card-body">
                <div class="card-top">
                    <span class="card-verified">✔ Verificado</span>
                </div>
                <div class="card-price"><sup>$</sup>200</div>
                <div class="card-title">Remodelaciones LM — Pintura, cerámica y carpintería</div>
                <div class="card-meta">
                    <span class="card-location">📍 Baruta</span>
                </div>
                <button class="btn-call" onclick="window.location='login.php'">📞 Ver Contacto</button>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<div class="cta-strip">
    <div class="cta-inner">
        <div class="cta-text">
            <h2>¿Tienes un negocio o servicio?</h2>
            <p>Publica gratis y llega a miles de clientes en tu municipio. Solicita verificación y destaca tu emprendimiento.</p>
        </div>
        <div class="cta-btns">
            <a href="register.php" class="btn-hero-primary">Publicar Servicio</a>
            <button class="btn-hero-secondary" onclick="openModal()">Postular Verificación</button>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer>
    

<style>
    /* Usamos 'footer a' para ser más específicos que el navegador */
    footer .dev-team a.dev-link-pro, 
    footer .dev-team a.dev-link-pro:visited {
        color: #FF7043 !important; /* El naranja que quieres */
        text-decoration: none !important; /* Quita el subrayado morado */
        font-weight: bold !important;
        font-family: sans-serif;
        transition: all 0.3s ease;
        display: inline-block;
    }

    /* Efecto de brillo al pasar el mouse */
    footer .dev-team a.dev-link-pro:hover {
        color: #ffffff !important;
        text-shadow: 0 0 10px #FF7043, 0 0 20px #FF7043 !important;
        transform: translateY(-2px);
    }
</style>
    
    <div class="logo-text">SERVI-<span style="color:var(--orange)">JOB</span></div>
    <p>Servicios Laborales Online — Caracas, Venezuela</p>
<div class="credits-container" style="margin-top: 20px; text-align: center;">
    <p style="color: var(--text-muted); font-size: 13px; letter-spacing: 1px; margin-bottom: 10px; text-transform: uppercase;">
        — Desarrollado por —
    </p>
    <div class="dev-team" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 15px;">
        <a href="https://sites.google.com/view/jagreda/inicio" target="_blank" class="dev-link-pro">Julio Agreda</a>
        <a href="https://sites.google.com/view/blog-oscar-orta/información" target="_blank" class="dev-link-pro">Oscar Orta</a>
        <a href="https://sites.google.com/view/diegocedeno/página-principal" target="_blank" class="dev-link-pro">Diego Cedeño</a>
        <a href="https://sites.google.com/view/jhon-useche/inicio" target="_blank" class="dev-link-pro">Jhon Useche</a>
        <a href="https://sites.google.com/view/jesus-urbaneja/inicio" target="_blank" class="dev-link-pro">Daiker Lopez</a>
    </div>
</div>

<!-- MODAL VERIFICACION -->
<div id="modalVerify" class="modal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <h2>Postular Verificación</h2>
        <p>Sube tu documento de identidad para que el Administrador valide tu negocio.</p>
        <form action="verify.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Nombre del Negocio</label>
                <input type="text" name="nombre" placeholder="Ej: Plomería Express" required>
            </div>
            <div class="form-group">
                <label>Municipio</label>
                <select name="municipio">
                    <option value="chacao">Chacao</option>
                    <option value="baruta">Baruta</option>
                    <option value="sucre">Sucre</option>
                    <option value="libertador">Libertador</option>
                </select>
            </div>
            <div class="form-group">
                <label>Documento de Identidad</label>
                <input type="file" name="id_doc" required>
            </div>
            <button type="submit" class="form-submit">Enviar al Administrador</button>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalVerify').classList.add('open');
}
function closeModal() {
    document.getElementById('modalVerify').classList.remove('open');
}

function filtrar(btn, municipio) {
    document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.card').forEach(card => {
        if (municipio === 'todos' || card.dataset.municipio === municipio) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// Search filter
document.getElementById('searchInput').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.card').forEach(card => {
        const text = card.innerText.toLowerCase();
        card.style.display = text.includes(q) ? '' : 'none';
    });
});
</script>
</body>
</html>
