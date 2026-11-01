\documentclass[12pt,a4paper,landscape]{article}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage[brazil]{babel}
\usepackage{geometry}
\usepackage{graphicx}
\geometry{landscape,left=3cm,right=3cm,top=2.5cm,bottom=2.5cm}
\pagestyle{empty}
\setlength{\parindent}{0pt}

\begin{document}
\begin{center}
@if ($logo)
\includegraphics[height=2.2cm]{@php echo $logo; @endphp}\par\vspace{0.6cm}
@endif
{\large\bfseries @php echo $instituicao; @endphp}\par
{\normalsize @php echo $programa; @endphp}\par
\vspace{1.4cm}
{\Huge\bfseries CERTIFICADO}\par
\vspace{1.2cm}
\begin{minipage}{0.9\textwidth}
\large\centering
Certificamos que \textbf{@php echo $participante; @endphp} participou, como @php echo $papel; @endphp, da banca de @php echo $etapa; @endphp de @php echo $nivel; @endphp de \textbf{@php echo $discente; @endphp}, com o trabalho intitulado ``@php echo $titulo; @endphp'', realizada em @php echo $data; @endphp@php echo $local; @endphp, no @php echo $programa; @endphp (@php echo $sigla; @endphp) da @php echo $instituicao; @endphp.
\end{minipage}
\vfill
@php echo $coordenador; @endphp\par
Coordenador(a) do @php echo $sigla; @endphp\par
@if ($portaria !== '')
{\small @php echo $portaria; @endphp}\par
@endif
\end{center}
\end{document}
