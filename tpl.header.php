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
html, body {
	background: #ffc426;
	color: black;
}
a {
	color: black;
}

.movie {
	background: black;
	margin-bottom: 10px;
	padding: 10px;
	color: white;
}
.movie h3 {
	margin-top: 0;
}
.movie h3 a {
	color: inherit;
}
.movie a.arrow {
	text-decoration: none;
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
</style>
</head>

<body>
