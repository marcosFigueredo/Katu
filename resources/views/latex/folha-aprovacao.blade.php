\documentclass[12pt,a4paper]{article}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage[brazil]{babel}
\usepackage{ifthen}
\usepackage{geometry}
\geometry{left=2.5cm,right=2.5cm,top=2.2cm,bottom=2.2cm}
\pagestyle{empty}
\setlength{\parindent}{0pt}

\newboolean{ing}
\setboolean{ing}{false}

\makeatletter
\def\@autor{@php echo $autor; @endphp}
\def\titulo{@php echo $titulo; @endphp}
\def\subtitulo{@php echo $subtitulo; @endphp}
\def\nomeUniversidade{@php echo $universidade; @endphp}
\def\nomeDepartamento{@php echo $departamento; @endphp}
\def\nomePrograma{@php echo $programa; @endphp}
\def\naturezaTrabalho{@php echo $natureza; @endphp}
\def\@localDefesa{@php echo $local; @endphp}
\def\@dataDefesa{@php echo $data; @endphp}
\def\@orientadorNome{@php echo $orientadorNome; @endphp}
\def\@orientadorFiliacao{@php echo $orientadorFiliacao; @endphp}
\def\@numeroMembrosBanca{@php echo $numeroMembros; @endphp}

\def\@mANome{@php echo $membros[0]['nome']; @endphp}\def\@mAFiliacao{@php echo $membros[0]['filiacao']; @endphp}\def\@mATipo{@php echo $membros[0]['tipo']; @endphp}
\def\@mBNome{@php echo $membros[1]['nome']; @endphp}\def\@mBFiliacao{@php echo $membros[1]['filiacao']; @endphp}\def\@mBTipo{@php echo $membros[1]['tipo']; @endphp}
\def\@mCNome{@php echo $membros[2]['nome']; @endphp}\def\@mCFiliacao{@php echo $membros[2]['filiacao']; @endphp}\def\@mCTipo{@php echo $membros[2]['tipo']; @endphp}
\def\@mDNome{@php echo $membros[3]['nome']; @endphp}\def\@mDFiliacao{@php echo $membros[3]['filiacao']; @endphp}\def\@mDTipo{@php echo $membros[3]['tipo']; @endphp}
\def\@mENome{@php echo $membros[4]['nome']; @endphp}\def\@mEFiliacao{@php echo $membros[4]['filiacao']; @endphp}\def\@mETipo{@php echo $membros[4]['tipo']; @endphp}

\newcommand{\ppgmsb@OrientadorBox}{%
	\noindent{\fontsize{12}{12}\selectfont \@orientadorNome\par}%
	\noindent{\fontsize{10}{12}\selectfont \@orientadorFiliacao\par}%
	\noindent{\fontsize{10}{12}\selectfont Orientador\par}%
}

\newcommand{\ppgmsb@MembroBox}[3]{%
	\noindent{\fontsize{12}{12}\selectfont #1\par}%
	\noindent{\fontsize{10}{12}\selectfont #2\par}%
	\noindent{\fontsize{10}{12}\selectfont
		\ifthenelse{\equal{1}{#3}}{Membro Interno}{Membro Externo}\par}%
}

\newcommand{\folhaAprovacao}{%
	\clearpage
	\thispagestyle{empty}
	\begin{center}
		{\LARGE\selectfont\bfseries \nomeUniversidade\par}
		{\Large \nomeDepartamento\par}
		{\large \nomePrograma\par}
		\vskip 1cm
		{\@autor\par}
		\vskip 0.5cm
		{\Large\selectfont \bfseries \titulo\par}
		{\large\selectfont \subtitulo\par}
		\vspace*{1cm}
		{\naturezaTrabalho\par}
		\vspace*{0.5cm}
		{\@localDefesa\par \@dataDefesa\par}
		\vspace{0.5cm}
		{\bfseries BANCA EXAMINADORA\par}
	\end{center}

	\ifthenelse{\equal{\@numeroMembrosBanca}{3}}{%
		\noindent\begin{minipage}[t]{0.48\textwidth}\vspace{0pt}%
			\ppgmsb@OrientadorBox
			\vspace{2.2\baselineskip}
			\ppgmsb@MembroBox{\@mBNome}{\@mBFiliacao}{\@mBTipo}
		\end{minipage}\hfill
		\begin{minipage}[t]{0.48\textwidth}\vspace{0pt}%
			\ppgmsb@MembroBox{\@mANome}{\@mAFiliacao}{\@mATipo}
		\end{minipage}
	}{}

	\ifthenelse{\equal{\@numeroMembrosBanca}{4}}{%
		\noindent\begin{minipage}[t]{0.48\textwidth}\vspace{0pt}%
			\ppgmsb@OrientadorBox
			\vspace{2.2\baselineskip}
			\ppgmsb@MembroBox{\@mBNome}{\@mBFiliacao}{\@mBTipo}
		\end{minipage}\hfill
		\begin{minipage}[t]{0.48\textwidth}\vspace{0pt}%
			\ppgmsb@MembroBox{\@mANome}{\@mAFiliacao}{\@mATipo}
			\vspace{2.2\baselineskip}
			\ppgmsb@MembroBox{\@mCNome}{\@mCFiliacao}{\@mCTipo}
		\end{minipage}
	}{}

	\ifthenelse{\equal{\@numeroMembrosBanca}{5}}{%
		\noindent\begin{minipage}[t]{0.48\textwidth}\vspace{0pt}%
			\ppgmsb@OrientadorBox
			\vspace{2.2\baselineskip}
			\ppgmsb@MembroBox{\@mBNome}{\@mBFiliacao}{\@mBTipo}
			\vspace{2.2\baselineskip}
			\ppgmsb@MembroBox{\@mDNome}{\@mDFiliacao}{\@mDTipo}
		\end{minipage}\hfill
		\begin{minipage}[t]{0.48\textwidth}\vspace{0pt}%
			\ppgmsb@MembroBox{\@mANome}{\@mAFiliacao}{\@mATipo}
			\vspace{2.2\baselineskip}
			\ppgmsb@MembroBox{\@mCNome}{\@mCFiliacao}{\@mCTipo}
		\end{minipage}
	}{}

	\ifthenelse{\equal{\@numeroMembrosBanca}{6}}{%
		\noindent\begin{minipage}[t]{0.48\textwidth}\vspace{0pt}%
			\ppgmsb@OrientadorBox
			\vspace{2.0\baselineskip}
			\ppgmsb@MembroBox{\@mBNome}{\@mBFiliacao}{\@mBTipo}
			\vspace{2.0\baselineskip}
			\ppgmsb@MembroBox{\@mDNome}{\@mDFiliacao}{\@mDTipo}
		\end{minipage}\hfill
		\begin{minipage}[t]{0.48\textwidth}\vspace{0pt}%
			\ppgmsb@MembroBox{\@mANome}{\@mAFiliacao}{\@mATipo}
			\vspace{2.0\baselineskip}
			\ppgmsb@MembroBox{\@mCNome}{\@mCFiliacao}{\@mCTipo}
			\vspace{2.0\baselineskip}
			\ppgmsb@MembroBox{\@mENome}{\@mEFiliacao}{\@mETipo}
		\end{minipage}
	}{}
}
\makeatother

\begin{document}
\folhaAprovacao
\end{document}
