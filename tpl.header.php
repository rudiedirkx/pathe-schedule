<!doctype html>
<html lang="en">

<head>
<meta name="viewport" content="initial-scale=1" />
<meta name="theme-color" content="#333" />
<link rel="icon" type="image/png" href="/favicon-128.png" sizes="128x128" />
<link rel="icon" href="/favicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon" />
<title><?= html($title ? "$title - " : '') ?>Pathe</title>
<style>
:root {
	--yellow: #ffc426;
	--color-text: #000;
	--color-background: var(--yellow);
	--color-movie-background: #000;
	--color-movie-text: #fff;
}
html {
	background: var(--color-background);
	color: var(--color-text);
	font-family: sans-serif;
}
a {
	color: var(--color-text);
}

.movie {
	background: var(--color-movie-background);
	margin-bottom: 10px;
	padding: 10px;
	color: var(--color-movie-text);
}
.movie h3 {
	margin-top: 0;
}
.movie h3 a {
	color: inherit;
}
.movie a.icon {
	text-decoration: none;
	margin-left: 0.5rem;
}
.movie button.icon {
	background: transparent;
	padding: 0;
	border: 0;
	margin-left: 0.5rem;
}
.movie ul,
.movie.hide h3 {
	margin-bottom: 0;
}

.movie h3 {
	color: lightblue;
}
.movie.todo h3 {
	color: green;
}
.movie.hide h3 {
	color: red;
}

.progress {
	background-color: white;
	position: relative;
	top: -2px;
}
.progress > .done {
	height: 2px;
	background-color: green;
}

@media (prefers-color-scheme: dark) {
	:root {
		--color-text: var(--yellow);
		--color-background: #000;
		--color-movie-background: #111;
		--color-movie-text: var(--yellow);
	}
}
</style>
</head>

<body>
