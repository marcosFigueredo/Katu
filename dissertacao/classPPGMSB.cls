%==============================================================================
% Estilo para teses/dissertações do PGMSB-UNEB:
% baseada na classe memoir
% Autoria: prof. Marcos Batista Figueredo
% email: mbfigueredo@uneb.br
% Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas --- PPGMSB
% Alagoinhas, Bahia, Brasil
% http://www.ppgmsb.uneb.br
%==============================================================================
%% This work may be distributed and/or modified under the
%% conditions of the LaTeX Project Public License, either version 1.3c
%% of this license or (at your option) any later version.
%% The latest version of this license is in
%%   https://www.latex-project.org/lppl.txt
%% and version 1.3c or later is part of all distributions of LaTeX
%% version 2008 or later.
%%
%% This work has the LPPL maintenance status `maintained'.
%%
%% The Current Maintainer of this work is Marcos Batista Figueredo.
%%
%% This work consists of the file classPPGMSB.cls
%% and the template file dissertacao.tex
%==============================================================================
% definição da classe e versão
\NeedsTeXFormat{LaTeX2e}	
\ProvidesClass{classPPGMSB}[2025/12/01 Classe padrao teses]
%Versão 1.0.0
%pacotes inciais
\RequirePackage{ifthen}
\RequirePackage{iftex}
% Limitação de caracteres no título

% Definições iniciais
\newboolean{tese}
\newboolean{diss}
\newboolean{qlf}
\newboolean{ing}
\setboolean{tese}{false}
\setboolean{diss}{false}
\setboolean{qlf}{false}
\setboolean{ing}{false}

%================== DEFININDO OPÇÕES DA CLASSE ==========
\DeclareOption{tese}{\setboolean{tese}{true}}
\DeclareOption{tese=en}{\setboolean{tese}{true}\setboolean{ing}{true}}
\DeclareOption{diss}{\setboolean{diss}{true}}
\DeclareOption{diss=en}{\setboolean{diss}{true}\setboolean{ing}{true}}
\DeclareOption{qlf}{\setboolean{qlf}{true}}
\DeclareOption{ing}{\setboolean{ing}{true}}

% Repassa demais opcoes a memoir	
\DeclareOption*{%
	\PassOptionsToClass{\CurrentOption}{memoir}%
}
\ProcessOptions\relax
\LoadClass[oneside]{memoir}

%================== VALIDAÇÃO DAS OPÇÕES ==========
\newcommand{\ppgmsb@validarOpcoes}{%
    \newcounter{ppgmsb@tipocount}%
    \setcounter{ppgmsb@tipocount}{0}%
    \ifthenelse{\boolean{tese}}{\stepcounter{ppgmsb@tipocount}}{}%
    \ifthenelse{\boolean{diss}}{\stepcounter{ppgmsb@tipocount}}{}%
    \ifthenelse{\boolean{qlf}}{\stepcounter{ppgmsb@tipocount}}{}%
    \ifnum\value{ppgmsb@tipocount}=0\relax
        \ClassError{classPPGMSB}{Nenhum tipo de documento especificado}{Use uma das opcoes: tese, diss, qlf, tese=en ou diss=en.}%
    \fi
    \ifnum\value{ppgmsb@tipocount}>1\relax
        \ClassError{classPPGMSB}{Multiplos tipos de documento especificados}{Use apenas uma das opcoes: tese, diss, qlf, tese=en ou diss=en.}%
    \fi
    \ifthenelse{\boolean{qlf}\AND\boolean{ing}}{%
        \ClassError{classPPGMSB}{Qualificacao nao suporta idioma ingles}{Qualificacoes devem ser sempre em portugues. Use apenas qlf.}%
    }{}%
}
\ppgmsb@validarOpcoes


%================== DEFININDO OPÇÕES DA CLASSE ==========

\RequirePackage{etoolbox} % Para usar \ifdefempty
\RequirePackage{environ} % Adicionado para permitir \Collect@Body
\RequirePackage{xstring}
\RequirePackage{xcolor}
\RequirePackage{lastpage}

%========================================================


%==============================================================================
% MENSAGENS DE ERRO E VALIDAÇÃO
%==============================================================================
\newcommand{\ppgmsb@erro}[2]{%
	\ClassError{classPPGMSB}{#1}{#2}%
}

\newcommand{\ppgmsb@aviso}[1]{%
	\ClassWarning{classPPGMSB}{#1}%
}

%==============================================================================


%==============================================================================
% VALIDAÇÃO GENÉRICA DE CAMPOS COM LIMITE
%==============================================================================
% Função genérica de validação
% #1 = texto
% #2 = limite de caracteres
% #3 = nome do campo (para mensagem)

\newcommand{\validarCampo}[3]{%
	\StrLen{#1}[\@temp@length]%
	\ifnum\@temp@length>#2\relax
	\@tempcnta=\@temp@length\relax
	\advance\@tempcnta by -#2\relax
	\colorbox{yellow}{\textbf{\textcolor{red}{EXCEDEU #3: +\the\@tempcnta\ caracteres}}}\\[2pt]%
	\StrLeft{#1}{#2}[\@parte@valida]%
	\StrGobbleLeft{#1}{#2}[\@parte@excedente]%
	\@parte@valida\colorbox{yellow}{\@parte@excedente}%
	\else
	#1%
	\fi
}

%========================================================
%===================== COMANDOS =========================
%========================================================

\def\@trabalhoNatureza{}
\newcommand{\TrabalhoNatureza}[1]{\def\@trabalhoNatureza{#1}}
\newcommand{\naturezaTrabalho}{\@trabalhoNatureza}

%-----------------------------------------------------------
% COMANDOS DE INDENTIFICAÇÃO DA INSTITUIÇÃO
%------------------------------------------------------------
% O nome da instituição que oferece o programa de pós-graduação
\def\@universidade{}
\newcommand{\Universidade}[1]{\def\@universidade{#1}}
\newcommand{\nomeUniversidade}{\@universidade}

% O nome do Departamento (quando for o caso)
\def\@departamento{}
\newcommand{\Departamento}[1]{\def\@departamento{#1}}
\newcommand{\nomeDepartamento}{\@departamento}

% O nome do Programa
\def\@programa{}
\newcommand{\Programa}[1]{\def\@programa{#1}}
\newcommand{\nomePrograma}{\@programa}

% O nome do reitor 
\def\@reitor{}
\newcommand{\ReitorNome}[1]{\def\@reitor{#1}}
\newcommand{\nomeReitor}{\@reitor}

% O nome do proReitor 
\def\@proreitor{}
\newcommand{\ProreitorNome}[1]{\def\@proreitor{#1}}
\newcommand{\nomeProreitor}{\@proreitor}

% O nome do Diretor do departametno
\def\@diretor{}
\newcommand{\DiretorNome}[1]{\def\@diretor{#1}}
\newcommand{\nomeDiretor}{\@diretor}

\def\@coordenador{}
\newcommand{\CoordenadorNome}[1]{\def\@coordenador{#1}}
\newcommand{\nomeCoordenador}{\@coordenador}

\def\@urluneb{}
\newcommand{\UrlUNEB}[1]{\def\@urluneb{#1}}
\newcommand{\urlUNEB}{\@urluneb}

\def\@urlmsb{}
\newcommand{\UrlMSB}[1]{\def\@urlmsb{#1}}
\newcommand{\urlMSB}{\@urlmsb}

%-----------------------------------------------------------
% COMANDOS DE INDENTIFICAÇÃO DO TRABALHO
%-----------------------------------------------------------

%========== AUTOR DO TRABALHO ===========================
\def\@autor{}
\newcommand{\NomeAutor}[1]{\def\@autor{#1}}
\newcommand{\autorNome}{\@autor}

\def\@email{}
\newcommand{\EmailAutor}[1]{\def\@email{#1}}
\newcommand{\emailAutor}{\@email}

\def\@linklattes{}
\newcommand{\LinkLattes}[1]{\def\@linklattes{#1}}
\newcommand{\linkLattes}{\@linklattes}

\def\@linkorcid{}
\newcommand{\LinkOrcid}[1]{\def\@linkorcid{#1}}
\newcommand{\linkOrcid}{\@linkorcid}

\def\@autornacionalidade{}
\newcommand{\AutorNacionalidade}[1]{\def\@autornacionalidade{#1}}
\newcommand{\autorNacionalidade}{\@autornacionalidade}

\def\@autormunicipio{}
\newcommand{\AutorMunicipio}[1]{\def\@autormunicipio{#1}}
\newcommand{\autorMunicipio}{\@autormunicipio}

\def\@autorestado{}
\newcommand{\AutorEstado}[1]{\def\@autorestado{#1}}
\newcommand{\autorEstado}{\@autorestado}

% Tipo do Trabbalho -  DISSERTAÇÃO TESE OU QUALIFICAÇÃO
\def\@tipo{}
\newcommand{\Tipo}[1]{\def\@tipo{#1}} % setter
\newcommand{\tipo}{\@tipo}   % getter 

\def\@datanascimento{}
\newcommand{\DataNascimento}[1]{\def\@datanascimento{#1}} % setter
\newcommand{\dataNascimento}{\@datanascimento}   % getter 

\def\@msbdoi{}
\newcommand{\Msbdoi}[1]{\def\@msbdoi{#1}}
\newcommand{\msbDOI}{\href{https://doi.org/\@msbdoi}{DOI: \@msbdoi}}

% ================================
% Data, Hora e Local da Defesa
% ================================

\def\@dataDefesa{}
\newcommand{\DataDefesa}[1]{\def\@dataDefesa{#1}}
\newcommand{\dataDefesa}{\@dataDefesa}

\def\@horaDefesa{}
\newcommand{\HoraDefesa}[1]{\def\@horaDefesa{#1}}
\newcommand{\horaDefesa}{\@horaDefesa}

\def\@localDefesa{}
\newcommand{\LocalDefesa}[1]{\def\@localDefesa{#1}}
\newcommand{\localDefesa}{\@localDefesa}


%========== DADOS DO TRABALHO ===========================
% Armazena os títulos
\def\@tituloBR{}
\def\@tituloEN{}

% Setters
\newcommand{\TituloBR}[1]{\def\@tituloBR{#1}}
\newcommand{\TituloEN}[1]{\def\@tituloEN{#1}}

% Getters validados
\newcommand{\tituloValidadoBR}{\validarCampo{\@tituloBR}{150}{Título PT}}
\newcommand{\tituloValidadoEN}{\validarCampo{\@tituloEN}{150}{Título EN}}

% Getter automático (escolhe idioma)
\newcommand{\titulo}{%
	\ifthenelse{\boolean{ing}}{%
		\tituloValidadoEN
	}{%
		\tituloValidadoBR
	}%
}
% =========== SUBTÍTULO EM PORTUGUÊS E INGLÊS =================== %

% Armazena os títulos
\def\@subtituloBR{}
\def\@subtituloEN{}

% Setters
\newcommand{\SubTituloBR}[1]{\def\@subtituloBR{#1}}
\newcommand{\SubTituloEN}[1]{\def\@subtituloEN{#1}}

% Getters validados
\newcommand{\subtituloValidadoBR}{\validarCampo{\@subtituloBR}{80}{sub Título PT}}
\newcommand{\subtituloValidadoEN}{\validarCampo{\@subtituloEN}{80}{Título EN}}

\newcommand{\subtitulo}{%
	\ifthenelse{\boolean{ing}}{
		\subtituloValidadoEN
	}{
		\subtituloValidadoBR
	}
}
%=====================================================
% PALAVRAS-CHAVE (PT/EN) + VALIDAÇÃO (3 a 7; separador ";")
%=====================================================
\def\@palavrasChavePT{}
\def\@palavrasChaveEN{}
\def\@palavrasChaveBusca{}

\newcommand{\ppgmsb@validarPalavrasChave}[2]{%
	\begingroup
	\if\relax\detokenize{#2}\relax
	\else
		\StrCount{#2}{;}[\ppgmsb@qtdsep]%
		\@tempcnta=\ppgmsb@qtdsep\relax
		\advance\@tempcnta by 1\relax
		\ifnum\@tempcnta<3\relax
			\ppgmsb@aviso{#1 deve conter entre 3 e 7 termos separados por ponto e virgula. Foram informados \the\@tempcnta\space termo(s).}%
		\else\ifnum\@tempcnta>7\relax
			\ppgmsb@aviso{#1 deve conter entre 3 e 7 termos separados por ponto e virgula. Foram informados \the\@tempcnta\space termos.}%
		\fi\fi
	\fi
	\endgroup
}

\newcommand{\palavrasChavePT}[1]{%
	\ppgmsb@validarPalavrasChave{palavrasChavePT}{#1}%
	\gdef\@palavrasChavePT{#1}%
}
\newcommand{\palavrasChaveEN}[1]{%
	\ppgmsb@validarPalavrasChave{palavrasChaveEN}{#1}%
	\gdef\@palavrasChaveEN{#1}%
}
\newcommand{\palavrasChaveBusca}[1]{\gdef\@palavrasChaveBusca{#1}}




% =========== DADOS PARA CATALOGAÇÃO =================== %
\def\@urltrabalho{}
\newcommand{\UrlTrabalho}[1]{\def\@urltrabalho{#1}}
\newcommand{\urlTrabalho}{\@urltrabalho}

% Comando que retorna o número total de páginas
\newcommand{\numeroDePaginas}{\pageref{LastPage}}

%=====================================================================
% Contadores globais
%=====================================================================
\newcounter{TotalFiguras}
\newcounter{TotalTabelas}

% Incrementos automáticos
\AtBeginEnvironment{figure}{\stepcounter{TotalFiguras}}
\AtBeginEnvironment{table}{\stepcounter{TotalTabelas}}

% Comandos para exibir resultados
\newcommand{\ContarFiguras}{\arabic{TotalFiguras}}
\newcommand{\ContarTabelas}{\arabic{TotalTabelas}}

% =========== DADOS de ADERÊNCIA =================== %

%-------------------------
% GRUPO DE PESQUISA
%-------------------------
\def\@grupoPesquisaBR{}
\def\@grupoPesquisaEN{}

\newcommand{\GrupoDePesquisaBR}[1]{\def\@grupoPesquisaBR{#1}}
\newcommand{\GrupoDePesquisaEN}[1]{\def\@grupoPesquisaEN{#1}}

\newcommand{\grupoPesquisa}{%
	\ifthenelse{\boolean{ing}}{\@grupoPesquisaEN}{\@grupoPesquisaBR}%
}

%-------------------------
% ÁREA DE CONCENTRAÇÃO
%-------------------------
\def\@areaDeConcentracaoBR{}
\def\@areaDeConcentracaoEN{}

\newcommand{\AreaDeConcentracaoBR}[1]{\def\@areaDeConcentracaoBR{#1}}
\newcommand{\AreaDeConcentracaoEN}[1]{\def\@areaDeConcentracaoEN{#1}}

\newcommand{\areaDeConcentracao}{%
	\ifthenelse{\boolean{ing}}{\@areaDeConcentracaoEN}{\@areaDeConcentracaoBR}%
}

%-------------------------
% LINHA DE PESQUISA
%-------------------------
\def\@linhaPesquisaBR{}
\def\@linhaPesquisaEN{}

\newcommand{\LinhaPesquisaBR}[1]{\def\@linhaPesquisaBR{#1}}
\newcommand{\LinhaPesquisaEN}[1]{\def\@linhaPesquisaEN{#1}}

\newcommand{\linhaPesquisa}{%
	\ifthenelse{\boolean{ing}}{\@linhaPesquisaEN}{\@linhaPesquisaBR}%
}



% =============================================================================
% ORIENTADOR
% =============================================================================

%-------------------------
% Armazenamento interno
%-------------------------
\def\@orientadorNome{}
\def\@orientadorFiliacao{}
\def\@orientadorSigla{}
\def\@orientadorOrcid{}
\def\@orientadorLattes{}

%-------------------------
% Setters
%-------------------------
\newcommand{\OrientadorNome}[1]{\def\@orientadorNome{#1}}
\newcommand{\OrientadorFiliacao}[1]{\def\@orientadorFiliacao{#1}}
\newcommand{\OrientadorSigla}[1]{\def\@orientadorSigla{#1}}


\newcommand{\OrientadorOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@orientadorOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@orientadorOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@orientadorOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}

% Lattes: apenas número/ID (sem URL)
\newcommand{\OrientadorLattes}[1]{\def\@orientadorLattes{#1}}

%-------------------------
% Getters
%-------------------------
\newcommand{\orientadorNome}{\@orientadorNome}
\newcommand{\orientadorFiliacao}{\@orientadorFiliacao}
\newcommand{\orientadorSigla}{\@orientadorSigla}
\newcommand{\orientadorOrcid}{\@orientadorOrcid}
\newcommand{\orientadorLattes}{\@orientadorLattes}


% =============================================================================
% COORIENTADORES 
% =============================================================================

% ============================================================
% Coorientador A
% ============================================================
\def\@coorientadorANome{}
\def\@coorientadorAFiliacao{}
\def\@coorientadorASigla{}
\def\@coorientadorAOrcid{}
\def\@coorientadorALattes{}
\def\@coorientadorAFormacao{}
\def\@coorientadorAAtuacao{}

%-------------------------
% Setters
%-------------------------
\newcommand{\CoorientadorANome}[1]{\def\@coorientadorANome{#1}}
\newcommand{\CoorientadorAFiliacao}[1]{\def\@coorientadorAFiliacao{#1}}
\newcommand{\CoorientadorASigla}[1]{\def\@coorientadorASigla{#1}}
\newcommand{\CoorientadorAFormacao}[1]{\def\@coorientadorAFormacao{#1}}
\newcommand{\CoorientadorAAtuacao}[1]{\def\@coorientadorAAtuacao{#1}}

\newcommand{\CoorientadorAOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@coorientadorAOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@coorientadorAOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@coorientadorAOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}
\newcommand{\CoorientadorALattes}[1]{\def\@coorientadorALattes{#1}}

%-------------------------
% Getters
%-------------------------
\newcommand{\coorientadorANome}{\@coorientadorANome}
\newcommand{\coorientadorAFiliacao}{\@coorientadorAFiliacao}
\newcommand{\coorientadorASigla}{\@coorientadorASigla}
\newcommand{\coorientadorAFormacao}{\@coorientadorAFormacao}
\newcommand{\coorientadorAAtuacao}{\@coorientadorAAtuacao}
\newcommand{\coorientadorAOrcid}{\@coorientadorAOrcid}
\newcommand{\coorientadorALattes}{\@coorientadorALattes}

% ============================================================
% Coorientador B
% ============================================================
\def\@coorientadorBNome{}
\def\@coorientadorBFiliacao{}
\def\@coorientadorBSigla{}
\def\@coorientadorBOrcid{}
\def\@coorientadorBLattes{}
\def\@coorientadorBFormacao{}
\def\@coorientadorBAtuacao{}

\newcommand{\CoorientadorBNome}[1]{\def\@coorientadorBNome{#1}}
\newcommand{\CoorientadorBFiliacao}[1]{\def\@coorientadorBFiliacao{#1}}
\newcommand{\CoorientadorBSigla}[1]{\def\@coorientadorBSigla{#1}}
\newcommand{\CoorientadorBFormacao}[1]{\def\@coorientadorBFormacao{#1}}
\newcommand{\CoorientadorBAtuacao}[1]{\def\@coorientadorBAtuacao{#1}}

\newcommand{\CoorientadorBOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@coorientadorBOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@coorientadorBOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@coorientadorBOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}
\newcommand{\CoorientadorBLattes}[1]{\def\@coorientadorBLattes{#1}}

\newcommand{\coorientadorBNome}{\@coorientadorBNome}
\newcommand{\coorientadorBFiliacao}{\@coorientadorBFiliacao}
\newcommand{\coorientadorBSigla}{\@coorientadorBSigla}
\newcommand{\coorientadorBFormacao}{\@coorientadorBFormacao}
\newcommand{\coorientadorBAtuacao}{\@coorientadorBAtuacao}
\newcommand{\coorientadorBOrcid}{\@coorientadorBOrcid}
\newcommand{\coorientadorBLattes}{\@coorientadorBLattes}

% ============================================================
% Coorientador C
% ============================================================
\def\@coorientadorCNome{}
\def\@coorientadorCFiliacao{}
\def\@coorientadorCSigla{}
\def\@coorientadorCOrcid{}
\def\@coorientadorCLattes{}
\def\@coorientadorCFormacao{}
\def\@coorientadorCAtuacao{}

\newcommand{\CoorientadorCNome}[1]{\def\@coorientadorCNome{#1}}
\newcommand{\CoorientadorCFiliacao}[1]{\def\@coorientadorCFiliacao{#1}}
\newcommand{\CoorientadorCSigla}[1]{\def\@coorientadorCSigla{#1}}
\newcommand{\CoorientadorCFormacao}[1]{\def\@coorientadorCFormacao{#1}}
\newcommand{\CoorientadorCAtuacao}[1]{\def\@coorientadorCAtuacao{#1}}

\newcommand{\CoorientadorCOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@coorientadorCOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@coorientadorCOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@coorientadorCOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}
\newcommand{\CoorientadorCLattes}[1]{\def\@coorientadorCLattes{#1}}

\newcommand{\coorientadorCNome}{\@coorientadorCNome}
\newcommand{\coorientadorCFiliacao}{\@coorientadorCFiliacao}
\newcommand{\coorientadorCSigla}{\@coorientadorCSigla}
\newcommand{\coorientadorCFormacao}{\@coorientadorCFormacao}
\newcommand{\coorientadorCAtuacao}{\@coorientadorCAtuacao}
\newcommand{\coorientadorCOrcid}{\@coorientadorCOrcid}
\newcommand{\coorientadorCLattes}{\@coorientadorCLattes}


% =============================================================================
% MEMBROS DA BANCA (A–F) — apenas getters e setters
% =============================================================================

% ============================================================
% Membro A
% ============================================================
\def\@mANome{}\def\@mAFiliacao{}\def\@mASigla{}\def\@mATipo{}
\def\@mAOrcid{}\def\@mALattes{}\def\@mAFormacao{}\def\@mAAtuacao{}

\newcommand{\MembroANome}[1]{\def\@mANome{#1}}
\newcommand{\MembroAFiliacao}[1]{\def\@mAFiliacao{#1}}
\newcommand{\MembroASigla}[1]{\def\@mASigla{#1}}
\newcommand{\MembroATipo}[1]{\def\@mATipo{#1}}
\newcommand{\MembroAFormacao}[1]{\def\@mAFormacao{#1}}
\newcommand{\MembroAAtuacao}[1]{\def\@mAAtuacao{#1}}

\newcommand{\MembroAOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@mAOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@mAOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@mAOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}
\newcommand{\MembroALattes}[1]{\def\@mALattes{#1}}

\newcommand{\mANome}{\@mANome}
\newcommand{\mAFiliacao}{\@mAFiliacao}
\newcommand{\mASigla}{\@mASigla}
\newcommand{\mATipo}{\@mATipo}
\newcommand{\mAFormacao}{\@mAFormacao}
\newcommand{\mAAtuacao}{\@mAAtuacao}
\newcommand{\mAOrcid}{\@mAOrcid}
\newcommand{\mALattes}{\@mALattes}

% ============================================================
% Membro B
% ============================================================
\def\@mBNome{}\def\@mBFiliacao{}\def\@mBSigla{}\def\@mBTipo{}
\def\@mBOrcid{}\def\@mBLattes{}\def\@mBFormacao{}\def\@mBAtuacao{}

\newcommand{\MembroBNome}[1]{\def\@mBNome{#1}}
\newcommand{\MembroBFiliacao}[1]{\def\@mBFiliacao{#1}}
\newcommand{\MembroBSigla}[1]{\def\@mBSigla{#1}}
\newcommand{\MembroBTipo}[1]{\def\@mBTipo{#1}}
\newcommand{\MembroBFormacao}[1]{\def\@mBFormacao{#1}}
\newcommand{\MembroBAtuacao}[1]{\def\@mBAtuacao{#1}}

\newcommand{\MembroBOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@mBOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@mBOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@mBOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}
\newcommand{\MembroBLattes}[1]{\def\@mBLattes{#1}}

\newcommand{\mBNome}{\@mBNome}
\newcommand{\mBFiliacao}{\@mBFiliacao}
\newcommand{\mBSigla}{\@mBSigla}
\newcommand{\mBTipo}{\@mBTipo}
\newcommand{\mBFormacao}{\@mBFormacao}
\newcommand{\mBAtuacao}{\@mBAtuacao}
\newcommand{\mBOrcid}{\@mBOrcid}
\newcommand{\mBLattes}{\@mBLattes}

% ============================================================
% Membro C
% ============================================================
\def\@mCNome{}\def\@mCFiliacao{}\def\@mCSigla{}\def\@mCTipo{}
\def\@mCOrcid{}\def\@mCLattes{}\def\@mCFormacao{}\def\@mCAtuacao{}

\newcommand{\MembroCNome}[1]{\def\@mCNome{#1}}
\newcommand{\MembroCFiliacao}[1]{\def\@mCFiliacao{#1}}
\newcommand{\MembroCSigla}[1]{\def\@mCSigla{#1}}
\newcommand{\MembroCTipo}[1]{\def\@mCTipo{#1}}
\newcommand{\MembroCFormacao}[1]{\def\@mCFormacao{#1}}
\newcommand{\MembroCAtuacao}[1]{\def\@mCAtuacao{#1}}

\newcommand{\MembroCOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@mCOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@mCOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@mCOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}
\newcommand{\MembroCLattes}[1]{\def\@mCLattes{#1}}

\newcommand{\mCNome}{\@mCNome}
\newcommand{\mCFiliacao}{\@mCFiliacao}
\newcommand{\mCSigla}{\@mCSigla}
\newcommand{\mCTipo}{\@mCTipo}
\newcommand{\mCFormacao}{\@mCFormacao}
\newcommand{\mCAtuacao}{\@mCAtuacao}
\newcommand{\mCOrcid}{\@mCOrcid}
\newcommand{\mCLattes}{\@mCLattes}

% ============================================================
% Membro D
% ============================================================
\def\@mDNome{}\def\@mDFiliacao{}\def\@mDSigla{}\def\@mDTipo{}
\def\@mDOrcid{}\def\@mDLattes{}\def\@mDFormacao{}\def\@mDAtuacao{}

\newcommand{\MembroDNome}[1]{\def\@mDNome{#1}}
\newcommand{\MembroDFiliacao}[1]{\def\@mDFiliacao{#1}}
\newcommand{\MembroDSigla}[1]{\def\@mDSigla{#1}}
\newcommand{\MembroDTipo}[1]{\def\@mDTipo{#1}}
\newcommand{\MembroDFormacao}[1]{\def\@mDFormacao{#1}}
\newcommand{\MembroDAtuacao}[1]{\def\@mDAtuacao{#1}}

\newcommand{\MembroDOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@mDOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@mDOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@mDOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}
\newcommand{\MembroDLattes}[1]{\def\@mDLattes{#1}}

\newcommand{\mDNome}{\@mDNome}
\newcommand{\mDFiliacao}{\@mDFiliacao}
\newcommand{\mDSigla}{\@mDSigla}
\newcommand{\mDTipo}{\@mDTipo}
\newcommand{\mDFormacao}{\@mDFormacao}
\newcommand{\mDAtuacao}{\@mDAtuacao}
\newcommand{\mDOrcid}{\@mDOrcid}
\newcommand{\mDLattes}{\@mDLattes}

% ============================================================
% Membro E
% ============================================================
\def\@mENome{}\def\@mEFiliacao{}\def\@mESigla{}\def\@mETipo{}
\def\@mEOrcid{}\def\@mELattes{}\def\@mEFormacao{}\def\@mEAtuacao{}

\newcommand{\MembroENome}[1]{\def\@mENome{#1}}
\newcommand{\MembroEFiliacao}[1]{\def\@mEFiliacao{#1}}
\newcommand{\MembroESigla}[1]{\def\@mESigla{#1}}
\newcommand{\MembroETipo}[1]{\def\@mETipo{#1}}
\newcommand{\MembroEFormacao}[1]{\def\@mEFormacao{#1}}
\newcommand{\MembroEAtuacao}[1]{\def\@mEAtuacao{#1}}

\newcommand{\MembroEOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@mEOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@mEOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@mEOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}
\newcommand{\MembroELattes}[1]{\def\@mELattes{#1}}

\newcommand{\mENome}{\@mENome}
\newcommand{\mEFiliacao}{\@mEFiliacao}
\newcommand{\mESigla}{\@mESigla}
\newcommand{\mETipo}{\@mETipo}
\newcommand{\mEFormacao}{\@mEFormacao}
\newcommand{\mEAtuacao}{\@mEAtuacao}
\newcommand{\mEOrcid}{\@mEOrcid}
\newcommand{\mELattes}{\@mELattes}

% ============================================================
% Membro F
% ============================================================
\def\@mFNome{}\def\@mFFiliacao{}\def\@mFSigla{}\def\@mFTipo{}
\def\@mFOrcid{}\def\@mFLattes{}\def\@mFFormacao{}\def\@mFAtuacao{}

\newcommand{\MembroFNome}[1]{\def\@mFNome{#1}}
\newcommand{\MembroFFiliacao}[1]{\def\@mFFiliacao{#1}}
\newcommand{\MembroFSigla}[1]{\def\@mFSigla{#1}}
\newcommand{\MembroFTipo}[1]{\def\@mFTipo{#1}}
\newcommand{\MembroFFormacao}[1]{\def\@mFFormacao{#1}}
\newcommand{\MembroFAtuacao}[1]{\def\@mFAtuacao{#1}}

\newcommand{\MembroFOrcid}[1]{%
	\begingroup
	\def\tmpa{#1}%
	\StrSubstitute{\tmpa}{~}{}[\tmpa]%
	\StrSubstitute{\tmpa}{ }{}[\tmpa]%
	\IfSubStr{\tmpa}{-}{%
		\xdef\@mFOrcid{\tmpa}%
	}{%
		\StrLen{\tmpa}[\tmplen]%
		\ifnum\tmplen=16
		\StrMid{\tmpa}{1}{4}[\pI]%
		\StrMid{\tmpa}{5}{8}[\pII]%
		\StrMid{\tmpa}{9}{12}[\pIII]%
		\StrMid{\tmpa}{13}{16}[\pIV]%
		\xdef\@mFOrcid{\pI-\pII-\pIII-\pIV}%
		\else
		\xdef\@mFOrcid{\tmpa}%
		\fi
	}%
	\endgroup
}
\newcommand{\MembroFLattes}[1]{\def\@mFLattes{#1}}

\newcommand{\mFNome}{\@mFNome}
\newcommand{\mFFiliacao}{\@mFFiliacao}
\newcommand{\mFSigla}{\@mFSigla}
\newcommand{\mFTipo}{\@mFTipo}
\newcommand{\mFFormacao}{\@mFFormacao}
\newcommand{\mFAtuacao}{\@mFAtuacao}
\newcommand{\mFOrcid}{\@mFOrcid}
\newcommand{\mFLattes}{\@mFLattes}



% =============================================================================
% OPÇÕES DO DOCUMENTO
% Tipo de texto para a contra-capa
% =============================================================================

\newcommand{\opcoesDocumento}{
	\ifthenelse{\boolean{tese}}{
		\ifthenelse{\boolean{ing}}{%valor se verdadeiro
			\TrabalhoNatureza{Thesis submitted to the Bahia State University, in the Postgraduate Program in Modeling and Simulation of Biosystems, as a partial requirement for obtaining the title of PhD in Biosystems Modeling and Simulation.}
			\Tipo{PhD Thesis}
		}{%valor se falso
			\TrabalhoNatureza{Tese de Doutorado submetida a Universidade do Estado da Bahia, no Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas, como requisito parcial para a obtenção do título de Doutor em Modelagem e Simulação de Biossistemas.}
			\Tipo{Tese}
		}
	}{}

	\ifthenelse{\boolean{diss}}{
		\ifthenelse{\boolean{ing}}{
			\TrabalhoNatureza{Master's dissertation submitted to the State University of Bahia, in the Graduate Program in Biosystems Modeling and Simulation, as a partial requirement to obtain the title of Master in Biosystems Modeling and Simulation.}
			\Tipo{Master's Dissertation}
		}{
			\TrabalhoNatureza{Dissertação de Mestrado submetida a Universidade do Estado da Bahia, no Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas, como requisito parcial para a obtenção do título de Mestre em Modelagem e Simulação de Biossistemas.}
			\Tipo{Dissertação}
		}
	}{}
	\ifthenelse{\boolean{qlf}}{
		\TrabalhoNatureza{Exame de qualificação submetido a Universidade do Estado da Bahia, no Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas.}
		\Tipo{Exame de Qualificação}
	}{}

	% Ativa as listas de símbolos e abreviações (ordenadas pelo próprio LaTeX)
	\ppgmsb@ativarGlossarios
}

%====================== DEFININDO CORES PADRÃO =========
\definecolor{ppgmsbC1}{HTML}{00303A}
\definecolor{ppgmsbC2}{HTML}{266054}
\definecolor{ppgmsbC3}{HTML}{19A340}
%=======================================================

%====================== CAMINHOS DOS LOGOS =============
% Padrões — o usuário redefine em dissertacao.tex via:
%   \LogoUniversidade{caminho/para/logo.jpg}
%   \LogoPrograma{caminho/para/logo.png}
%   \LogoCabecalho{caminho/para/logo.png}
\def\@logoUniversidade{figuras/logo_uneb.jpg}
\def\@logoPrograma{figuras/ppgmsbLogo.png}
\def\@logoCabecalho{figuras/logoso.png}

\newcommand{\LogoUniversidade}[1]{\def\@logoUniversidade{#1}}
\newcommand{\LogoPrograma}[1]{\def\@logoPrograma{#1}}
\newcommand{\LogoCabecalho}[1]{\def\@logoCabecalho{#1}}

% Inclui imagem se o arquivo existir; caso contrário exibe quadrado com X
\newcommand{\ppgmsb@safeLogo}[2]{%
  \IfFileExists{#1}{%
    \includegraphics[height=#2]{#1}%
  }{%
    \begin{tikzpicture}[baseline=(current bounding box.south)]
      \draw[ppgmsbC2, line width=1pt] (0,0) rectangle (#2,#2);
      \draw[ppgmsbC2, line width=0.6pt] (0,0) -- (#2,#2);
      \draw[ppgmsbC2, line width=0.6pt] (#2,0) -- (0,#2);
    \end{tikzpicture}%
  }%
}
%=======================================================

%====================== DEFINIÇÕES GERAIS =========
% Defina as margens padrão do documento aqui (fora do comando)
\RequirePackage{geometry}
\geometry{
	a4paper,            % Define o tamanho do papel (A4)
	left=2.5cm,         % Margem esquerda
	right=2.5cm,        % Margem direita
	top=2.5cm,          % Margem superior
	bottom=2.5cm,       % Margem inferior
	headsep=1cm,      % Aumenta a distância entre cabeçalho e texto
	footskip=1cm,     % Ajusta a distância entre texto e rodapé
	includeheadfoot,    % Inclui cabeçalho e rodapé na área total
	heightrounded       % Ajusta a altura do texto para evitar erros
}

% Ajustar altura do cabeçalho para acomodar o logo (0.5cm ≈ 14.22pt)
\setlength{\headheight}{20pt}

% Espaçamento de 1,5 linhas
\OnehalfSpacing


% FONTE
\RequirePackage[T1]{fontenc}    % Codificação de fonte T1
\RequirePackage{lmodern} 

% Condição para compatibilidade com diferentes motores
\ifPDFTeX
    \RequirePackage[utf8]{inputenc} % Codificação de entrada UTF-8 para pdfLaTeX
\fi

% Declarar substituições de fonte explícitas para evitar warnings
% (executado após as fontes serem carregadas)
\AtBeginDocument{%
	\DeclareFontShape{T1}{lmss}{bx}{it}{<->ssub*lmss/bx/sl}{}%
	\DeclareFontShape{T1}{lmss}{bx}{sc}{<->ssub*lmss/bx/n}{}%
}

\RequirePackage{microtype}
\renewcommand\familydefault{\sfdefault}  
\renewcommand{\normalsize}{\fontsize{12}{14}\selectfont}  % Texto normal: 12pt
\renewcommand{\large}{\fontsize{14}{16}\selectfont}       % Equivalente a Word 14pt
\renewcommand{\Large}{\fontsize{16}{18}\selectfont}       % Equivalente a Word 16pt
\renewcommand{\LARGE}{\fontsize{18}{20}\selectfont}       % Equivalente a Word 18pt
\renewcommand{\huge}{\fontsize{20}{22}\selectfont}        % Compatível com telas grandes
\renewcommand{\Huge}{\fontsize{24}{26}\selectfont}        % Texto destacado compatível com Word/Web

\RequirePackage{graphicx}
\RequirePackage{tikz}

%\RequirePackage{algpseudocode}
\RequirePackage[linesnumbered,ruled,vlined]{algorithm2e}

\RequirePackage[english,brazilian]{babel} % Suporte para Inglês e Português Brasileiro
\RequirePackage{placeins}
%=======================================================

% ==================== PACOTES MATEMÁTICOS E FISICOS
\RequirePackage{amsmath}  % Melhorias na formatação matemática (\numberwithin, \text, etc.)
\RequirePackage{amssymb}  % Conjuntos adicionais de símbolos matemáticos
\RequirePackage{amsfonts} % Fontes matemáticas adicionais (ex: \mathbb)
%\RequirePackage{physics}  % Facilita a notação matemática de derivadas, vetores, tensores
%\RequirePackage{siunitx}  % Formatação correta de unidades físicas 
%\RequirePackage{mhchem}   % Notação química e reações químicas (ex: \ce{H2O})
%\RequirePackage{circuitikz} % Desenho de circuitos elétricos e eletrônicos
%\RequirePackage{tikz-feynman} % Diagramas de Feynman para Física de Partículas
\RequirePackage{pgfplots} % Gráficos científicos em LaTeX
\pgfplotsset{compat=1.18}

%======================== TABELAS E FIGURAS ALGORTIMOS ============
\renewcommand{\thefigure}{\thechapter.\arabic{figure}}
\numberwithin{figure}{chapter} % Garante que a numeração siga o capítulo

\renewcommand{\thetable}{\thechapter.\arabic{table}} % Formato Capítulo.Tabela
\numberwithin{table}{chapter} % Reinicia a numeração das tabelas a cada capítulo

% Configuração de numeração e tradução para algorithm2e
\renewcommand{\thealgocf}{\thechapter.\arabic{algocf}} % Formato Capítulo.Algoritmo
\numberwithin{algocf}{chapter} % Reinicia a numeração dos algoritmos a cada capítulo
\ifthenelse{\boolean{ing}}{%
\SetAlgorithmName{Algorithm}{Algorithm}{List of Algorithms}%
}{%
\SetAlgorithmName{Algoritmo}{Algoritmo}{Lista de Algoritmos}%
}


%======================== CAPA ============================
\newcommand{\makeCapa}{
	\thispagestyle{empty}
	\begingroup
	\begin{flushright}
		{\Huge\textcolor{ppgmsbC3}{\MakeUppercase{\tipo}}}
		\vskip 0.3cm
		\begingroup
		%\color{ppgmsbC1} % Define a cor da linha
		\begin{tikzpicture}
			\fill[ppgmsbC1] (0,0) rectangle (\textwidth, 2pt);
		\end{tikzpicture}%
		\endgroup
		\vskip 1cm
		{\LARGE\bfseries \titulo\par}
		\ifx\subtitulo\@empty
		% Não imprime nada se o subtítulo não estiver definido
		\else
		\vskip 0.5cm % Espaçamento entre título e subtítulo
		{\large \subtitulo\par}
		\fi
		\vskip 1.5cm
		{\large\bfseries \autorNome}
		\vskip 0.5cm
		{\emailAutor}
		\vskip 2cm
		{\bfseries \ifthenelse{\boolean{ing}}{supervision of:}{orientado por:}}	
		
		{\normalfont \orientadorNome}
				
		{\normalfont \orientadorFiliacao}
		
	\end{flushright}
	\vspace*{\fill} % Preenche todo o espaço disponível antes das imagens
	\noindent
	\begin{minipage}{\textwidth} % Minipage para garantir alinhamento correto
		\centering
		\ppgmsb@safeLogo{\@logoUniversidade}{2.5cm}
		\hfill
		\ppgmsb@safeLogo{\@logoPrograma}{2.5cm}
	\end{minipage}
	\endgroup
	\clearpage
}
%======================== FIM CAPA ============================

%=================================================================
%======================== CONTRA-CAPA ============================
%=================================================================

% Usa apenas comandos públicos e \msbDOI; imprime DOI só se definido
\newcommand{\makeContraCapa}{%
	\newpage
	\thispagestyle{empty}	
	\begin{center}
		{\LARGE\bfseries \nomeUniversidade\par}
		{\Large \nomeDepartamento\par}
		{\large\selectfont \nomePrograma\par}
		\vskip 2cm
		{\autorNome\par}
		\vskip 2cm
		{\Large \bfseries \titulo\par}
		\if\relax\detokenize{\subtitulo}\relax\else
		{\large \subtitulo\par}
		\fi
		\vskip 2cm
	\end{center}
	
	\begin{flushright}
		\begin{list}{}{\setlength{\leftmargin}{5cm}\setlength{\rightmargin}{0cm}%
				\setlength{\labelwidth}{0pt}\setlength{\labelsep}{\leftmargin}}%
			\item \normalfont \naturezaTrabalho
			
			\begin{list}{}{\setlength{\leftmargin}{0cm}\setlength{\rightmargin}{0cm}%
					\setlength{\labelwidth}{0pt}\setlength{\labelsep}{\leftmargin}}%
				
				\item \ifthenelse{\boolean{ing}}{Interdisciplinary Field}{Área de conhecimento: Interdisciplinar}
				\item \ifthenelse{\boolean{ing}}{Major Field: \areaDeConcentracao}{Área de Concentração: \areaDeConcentracao}
				\item \ifthenelse{\boolean{ing}}{Research Area: \linhaPesquisa}{Linha de Pesquisa: \linhaPesquisa}
				
				\item \ifthenelse{\boolean{ing}}{Advisor: }{Orientador: } \orientadorNome
				
				% Coorientadores opcionais (A..C)
				\ifx\@coorientadorANome\empty\else
				\item \ifthenelse{\boolean{ing}}{Co-advisor: }{Coorientador: } \coorientadorANome
				\ifx\@coorientadorASigla\empty\else~(\coorientadorASigla)\fi
				\fi
				\ifx\@coorientadorBNome\empty\else
				\item \ifthenelse{\boolean{ing}}{Co-advisor: }{Coorientador: } \coorientadorBNome
				\ifx\@coorientadorBSigla\empty\else~(\coorientadorBSigla)\fi
				\fi
				\ifx\@coorientadorCNome\empty\else
				\item \ifthenelse{\boolean{ing}}{Co-advisor: }{Coorientador: } \coorientadorCNome
				\ifx\@coorientadorCSigla\empty\else~(\coorientadorCSigla)\fi
				\fi
				
			\end{list}
		\end{list}
	\end{flushright}
	
	\begin{center}
		% URL do trabalho, se definida
		\if\relax\detokenize{\urlTrabalho}\relax\else
		\ifthenelse{\boolean{ing}}{URL}{URL do documento original: } \url{\urlTrabalho}\par
		\fi
		% DOI via \msbDOI, apenas quando \@msbdoi não está vazio
		\if\relax\detokenize{\@msbdoi}\relax\else
		\msbDOI
		\fi
	\end{center}
	
	\vfill
	\begin{center}
		\localDefesa\par \dataDefesa
	\end{center}
	
	\clearpage
}
%====================== FIM CONTRA-CAPA =========================

%======================== FOLHA DE APROVAÇÃO ============================
\newcommand{\@numeroMembrosBanca}{}
\newcommand{\numeroMembrosBanca}[1]{%
	\ifnum#1=3 \renewcommand{\@numeroMembrosBanca}{#1}%
	\else\ifnum#1=4 \renewcommand{\@numeroMembrosBanca}{#1}%
	\else\ifnum#1=5 \renewcommand{\@numeroMembrosBanca}{#1}%
	\else\ifnum#1=6 \renewcommand{\@numeroMembrosBanca}{#1}%
	\else
	\PackageError{banca}{Número de membros inválido: #1. Deve ser 3, 4, 5 ou 6.}{Escolha um valor válido.}
	\fi\fi\fi\fi
}


% ============================================
% Folha de Aprovação / Assinaturas (versão sem coorientador)
% ============================================
\makeatletter

\newcommand{\ppgmsb@OrientadorBox}{%
	\noindent{\fontsize{12}{12}\selectfont \@orientadorNome\par}%
	\noindent{\fontsize{10}{12}\selectfont \@orientadorFiliacao\par}%
	\noindent{\fontsize{10}{12}\selectfont
		\ifthenelse{\boolean{ing}}{Thesis Advisor}{Orientador}\par}%
}

\newcommand{\ppgmsb@MembroBox}[3]{%
	\noindent{\fontsize{12}{12}\selectfont #1\par}%
	\noindent{\fontsize{10}{12}\selectfont #2\par}%
	\noindent{\fontsize{10}{12}\selectfont
		\ifthenelse{\equal{1}{#3}}%
		{\ifthenelse{\boolean{ing}}{Internal Examiner}{Membro Interno}}%
		{\ifthenelse{\boolean{ing}}{External Examiner}{Membro Externo}}%
		\par}%
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
		{\bfseries \ifthenelse{\boolean{ing}}{THESIS COMMITTEE}{BANCA EXAMINADORA}\par}
	\end{center}
	
	% -------- 3 membros (Orientador + A + B) --------
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
	
	% -------- 4 membros (Orientador + A + B + C) --------
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
	
	% -------- 5 membros (Orientador + A + B + C + D) --------
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
	
	% -------- 6 membros (Orientador + A + B + C + D + E) --------
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
	
	\clearpage
}

\makeatother

%======================== CAPÍTULOS SESSÕES E SUBSESSÕES ============================

% chapter definition
\ifthenelse{\boolean{ing}}{}{\renewcommand{\chaptername}{CAPÍTULO}}

% Formatação do capítulo usando comandos da memoir
\makeatletter
\makechapterstyle{ppgmsb}{%
	\renewcommand{\chapterheadstart}{\vspace*{50pt}}
	\renewcommand{\printchaptername}{}
	\renewcommand{\chapternamenum}{}
	\renewcommand{\printchapternum}{%
		\hfill
		\rotatebox[origin=c]{90}{%
			\normalfont\color{black}\Large\textsc{\@chapapp}%
		}%
		\hspace{10pt}%
		\colorbox{ppgmsbC2}{%
			\parbox[c][3cm][c]{2.5cm}{%
				\centering\color{white}\fontsize{80}{90}\selectfont\thechapter
			}%
		}%
	}%
	\renewcommand{\afterchapternum}{\par\vskip 10pt}
	\renewcommand{\printchaptertitle}[1]{%
		{\LARGE\sffamily\raggedright ##1}%
		\vspace*{\baselineskip}
		{\color{ppgmsbC1}\hrule height 2.5pt}%
		
	}%
	\renewcommand{\afterchaptertitle}{\par\nobreak\vskip 30pt}%
}
\makeatother
\chapterstyle{ppgmsb}




%formatação section, subsection e subsubsections
% Configuração das Seções (Nível 1)
\setsecheadstyle{\Large\bfseries\color{ppgmsbC2}\raggedright}
\setbeforesecskip{-3.5ex plus -1ex minus -.2ex}
\setaftersecskip{2.3ex plus .2ex}

% Configuração das Subseções (Nível 2)
\setsubsecheadstyle{\large\bfseries\color{ppgmsbC3}\raggedright}
\setbeforesubsecskip{-3.25ex plus -1ex minus -.2ex}
\setaftersubsecskip{1.5ex plus .2ex}

% Configuração das Subsubseções (Nível 3) sem numeração, mas no sumário
\setsubsubsecheadstyle{\normalsize\color{ppgmsbC3}\itshape\raggedright}
\setbeforesubsubsecskip{-3.25ex plus -1ex minus -.2ex}
\setaftersubsubsecskip{1.5ex plus .2ex}

% Faz com que as subsubseções apareçam no sumário
\setsecnumdepth{subsection} % Define a profundidade de numeração até \subsection
\maxtocdepth{subsubsection} % Faz \subsubsection aparecer no sumário




%======================== FIM CAPÍTULOS SESSÕES E SUBSESSÕES ============================

%=========== ESPAÇAMENTOS ENTRE PARÁGRAFOS SESSÕES =======
\RequirePackage{indentfirst} % Indenta o primeiro parágrafo após seções
\setlength{\parindent}{1.27cm} % Define o recuo do parágrafo
\setlength{\parskip}{0pt}      % Remove espaço extra entre parágrafos
\OnehalfSpacing                % Define espaçamento de 1,5 linhas (APA)




%=================== AGRADECIMENTOS COM CORTE =======================
\makeatletter
\newcommand{\agradecimentosTexto}{} % onde armazenaremos o conteúdo

\newcommand{\processaAgradecimentos}[1]{%
	\def\agradecimentosTexto{#1}%
	\StrLen{\agradecimentosTexto}[\tamanhoAgradecimento]%
	\ifnum\tamanhoAgradecimento>1000
	\StrLeft{\agradecimentosTexto}{1000}[\agradecimentosTexto]%
	\textcolor{red}{\textbf{Aviso: o texto de agradecimentos foi truncado para 1000 caracteres.}}%
	\fi
	\agradecimentosTexto%
}

\newenvironment{agradecimentos}
{
	\ifthenelse{\boolean{ing}}{
		\chapter*{Acknowledgements}
	}{
		\chapter*{Agradecimentos}
	}
	\renewcommand{\agradecimentosTexto}{}%
	\Collect@Body\processaAgradecimentos
}
{}
\makeatother
%=================== DEDICATÓRIA =======================

%=================== DEDICATÓRIA COM LIMITE ==========================
\makeatletter
\newcommand{\dedicarTexto}{}  % Armazena o conteúdo digitado

\newcommand{\processaDedicar}[1]{%
	\def\dedicarTexto{#1}%
	\StrLen{\dedicarTexto}[\tamanhoDedicar]%
	\ifnum\tamanhoDedicar>400
	\StrLeft{\dedicarTexto}{400}[\dedicarTexto]%
	\textcolor{red}{\textbf{Aviso: a dedicatória foi truncada para 400 caracteres.}}%
	\fi
	
	% Mantém exatamente a estrutura original:
	\vspace*{\stretch{1}}  % Espaço no topo
	\begin{flushright}
		\begin{minipage}{0.6\textwidth}  % Reduz a largura
			\raggedleft
			\dedicarTexto
		\end{minipage}
	\end{flushright}
	\vspace*{\stretch{2}}  % Espaço no fim
}

\newenvironment{dedicar}
{   
	\ifthenelse{\boolean{ing}}{
		\chapter*{Dedication}
		%\addcontentsline{toc}{chapter}{Dedication}
	}{
		\chapter*{Dedicatória}
		%\addcontentsline{toc}{chapter}{Dedicatória}
	}
	\renewcommand{\dedicarTexto}{}%
	\Collect@Body\processaDedicar
}
{}
\makeatother

%=========================== RESMUNO LETRA VERNACULA ============
%======================= RESUMO ======================

\makeatletter
\newenvironment{resumo}
{
	\clearpage
	\chapter*{Resumo}
	\addcontentsline{toc}{chapter}{Resumo}
	\par\noindent\ignorespaces
}
{
	\ifx\@palavrasChavePT\@empty\else
	\vspace{2cm}\par\noindent\textbf{Palavras-chave:} \@palavrasChavePT
	\fi
	\clearpage
}
\makeatother




%====================== RESUMO FIM =================
%======================= ABSTRAC ===================
\makeatletter
\newenvironment{newAbstract}
{
	\clearpage
	\chapter*{Abstract}
	\addcontentsline{toc}{chapter}{Abstract}
	
	\par\noindent\ignorespaces
}
{
	\ifx\@palavrasChaveEN\@empty\else
	\vspace{2cm}\par\noindent\textbf{Keywords:} \@palavrasChaveEN
	\fi
	\clearpage
}
\makeatother

%======================= LISTAS ================
\makeatletter
\newcommand{\listaDeIlustracoes}{
	\begingroup
	\let\oldaddcontentsline\addcontentsline
	\renewcommand{\addcontentsline}[3]{}
	\ifthenelse{\boolean{ing}}{
		\chapter*{List of Figures}
	}{
		\chapter*{Lista de Ilustrações}
	}
	\@starttoc{lof}%  <-- Gera apenas a lista, sem nenhum título
	\let\addcontentsline\oldaddcontentsline
	\endgroup
	\clearpage
}

\newcommand{\listaDeTabelas}{
	\begingroup
	\let\oldaddcontentsline\addcontentsline
	\renewcommand{\addcontentsline}[3]{}
	\ifthenelse{\boolean{ing}}{
		\chapter*{List of Tables}
	}{
		\chapter*{Lista de Tabelas}
	}
	\@starttoc{lot}%  <-- Gera apenas a lista, sem nenhum título
	\let\addcontentsline\oldaddcontentsline
	\endgroup
	\clearpage
}

\makeatother

% ---
% ---



%======================= LISTA ALGORITMOS ========
\makeatletter

\SetKwComment{Comment}{/* }{ */}
\newcommand{\listaDeAlgoritmos}{%
\listofalgorithms
\cleardoublepage
}
\makeatother
%======================= COMANDOS DE SUMÁRIO ================
\ifthenelse{\boolean{ing}}{%
\renewcommand{\contentsname}{Contents}%
}{%
\renewcommand{\contentsname}{Sumário}%
}






%============================================================

%****************Comando Especiais ********************
\makeatletter
\newcommand{\sen}{\operatorname{sen}}
\newcommand{\senh}{\operatorname{senh}}
\newcommand{\tg}{\operatorname{tg}}
\newcommand{\tgh}{\operatorname{tgh}}
\newcommand{\cossec}{\operatorname{cossec}}
\newcommand{\cotg}{\operatorname{cotg}}
\newcommand{\cossech}{\operatorname{cossech}}
\newcommand{\cotgh}{\operatorname{cotgh}}
\newcommand{\arcsen}{\operatorname{arcsen}}

%cria o comando \chaves{arg1}
\newcommand{\chaves}[1]
{\ensuremath{\left\lbrace #1 \right\rbrace}}
\makeatother
%Define o estilo do texto matemático
\everymath{\displaystyle}

%======================CABEÇALHO E RODAPÉ ===============
% Criar um novo estilo de página
\makepagestyle{ppgmsbPages}
\makeheadfootruleprefix{ppgmsbPages}{\color{ppgmsbC1}}{\color{ppgmsbC1}}
\makeheadrule{ppgmsbPages}{\textwidth}{\normalrulethickness}
\makefootrule{ppgmsbPages}{\textwidth}{0.4pt}{4pt}
\makeevenfoot {ppgmsbPages}{}{\small  \thepage} {}
\makeoddfoot {ppgmsbPages}{}{\small \thepage} {}

% Configurar os marcadores
% Configurar os marcadores
\makepsmarks{ppgmsbPages}{%
	\nouppercaseheads
	\createmark{chapter}{both}{nonumber}{}{}%
	\createmark{section}{right}{nonumber}{}{}%
}
\makeatletter
\makeevenhead{ppgmsbPages}{\ppgmsb@safeLogo{\@logoCabecalho}{0.5cm}}{}%
{\small\itshape\MakeUppercase{\leftmark}}

\makeoddhead{ppgmsbPages}{\ppgmsb@safeLogo{\@logoCabecalho}{0.5cm}}{}%
{\small\itshape\MakeUppercase{\leftmark}}
\makeatother

%================ FIM CABEÇALHO E RODAPE ===================

%================ SISTEMA DE REFERECIAS======================
\RequirePackage[natbibapa,apaciteclassic]{apacite} % natbibapa para compatibilidade com o pacote natbib
\ifthenelse{\boolean{ing}}
{\renewcommand{\bibname}{References}} % Se 'ing' for verdadeiro
{\renewcommand{\bibname}{Referências}} % Se 'ing' for falso
\bibliographystyle{apacite}
\newenvironment{citacao}{
	\begin{flushright}
		\list{}%
		\footnotesize
		\addtolength{\leftskip}{2.27cm}%
		\item[]%

		\begin{singlespace}
			}{%

		\end{singlespace}
		\endlist
	\end{flushright}
}%



%================ COMANDO CITAÇÃO EM FIGURAS E TABELAS ======================
\RequirePackage{caption}
\newcommand{\fonte}[1]{%
	\par\vspace{2pt}%
	\captionsetup{font=small}
	\caption*{\textbf{Fonte:} \if\relax\detokenize{#1}\relax Próprio autor\else #1\fi}
}
%================ COMANDO NOTA PARA TABELAS ======================
\newcommand{\nota}[1]{
	\par
	\small
	\textbf{\textit{Nota}:} #1
	\par
}

%==============================================================================
% ODS — seleção única, nomes PT e imagens das selecionadas
%==============================================================================

\makeatletter

% SET: Armazena as ODS selecionadas
\newcommand{\odsSelecionadas}[1]{%
	\def\ods@lista{#1}%
}

% GET: Mostra as imagens das ODS em barra centralizada
\newcommand{\ODSFiguras}[1][2.5cm]{%
	\begin{center}
		\setlength{\fboxsep}{0pt}%
		\setlength{\fboxrule}{0pt}%
		\@for\num:=\ods@lista\do{%
			\IfFileExists{figuras/ods\num.png}{%
				\includegraphics[height=#1]{figuras/ods\num.png}%
			}{%
				\colorbox{ppgmsbC2}{%
					\vbox to #1{\vfil
						\hbox to #1{\hfil
							\textcolor{white}{\fontsize{6}{6}\selectfont\bfseries ODS\,\num}%
						\hfil}\vfil}%
				}%
			}%
		}%
	\end{center}
}

\makeatother

% =========================================================
% PPGMSB - CERTIFICAÇÃO DO TEMPLATE (FIXO)
% =========================================================
\newcommand{\PPGMSBCertToken}{PPGMSB-UNEB|classPPGMSB|CERTIFIED}

\makeatletter
\@ifpackageloaded{hyperref}{}{%
	\RequirePackage{hyperref}%
}
\makeatother

\AtBeginDocument{%
	\hypersetup{
		pdfproducer = {\PPGMSBCertToken},
		pdfcreator  = {\PPGMSBCertToken},
		pdfkeywords = {\PPGMSBCertToken}
	}%
}

%==============================================================================
% GLOSSÁRIOS: LISTA DE SÍMBOLOS E LISTA DE ABREVIAÇÕES
%==============================================================================
% As listas são ordenadas pelo próprio LaTeX (\makenoidxglossaries), sem
% makeglossaries, makeindex, xindy, bib2gls ou shell-escape. Basta compilar
% com pdflatex/xelatex/lualatex (ou latexmk), inclusive no Overleaf.
% Entradas: \newglossaryentry{...}{..., type=symbols} e \newabbreviation,
% sempre no preâmbulo. Listas: \listaDeSimbolos e \listaDeAbreviacoes.
\makeatletter
\RequirePackage[abbreviations,symbols,toc=false]{glossaries-extra}

% Documentos antigos carregam o pacote com "automake". Registrar essas opções
% como já usadas evita o erro "Option clash"; elas não têm efeito aqui.
\expandafter\g@addto@macro\csname opt@glossaries-extra.sty\endcsname{%
	,automake,automake=true,automake=false,automake=immediate,automake=lite,%
	automake=makegloss,automake=delayed}

% Estilo pontilhado: símbolo (ou sigla) : descrição .......... página
\newglossarystyle{dotglos-extra}{%
	\setglossarystyle{list}% Usa o estilo base "list"
	\renewcommand*{\glossentry}[2]{%
		\item \ifglshasfield{symbol}{##1}{\glsentrysymbol{##1}}{\glsentryshort{##1}} :
		\glossentrydesc{##1} \dotfill \glsentryitem{##1}%
		\glstarget{##1}{\textbf{##2}}%
	}
	\renewcommand*{\glsgroupskip}{}%
}
\setglossarystyle{dotglos-extra}

% Documentos antigos definem dotglos-extra no preâmbulo: em vez do erro
% "already defined", a definição do documento substitui a da classe.
\let\ppgmsb@newglossarystyle\newglossarystyle
\renewcommand{\newglossarystyle}[2]{%
	\ifboolexpr{test {\ifstrequal{#1}{dotglos-extra}} and test {\ifcsdef{@glsstyle@#1}}}{%
		\ppgmsb@aviso{O estilo dotglos-extra ja e definido pela classe.\MessageBreak
			Remova \string\newglossarystyle{dotglos-extra} do preambulo}%
		\csdef{@glsstyle@#1}{#2}%
	}{%
		\ppgmsb@newglossarystyle{#1}{#2}%
	}%
}

% Ordenação: \printnoidxglossary compara as chaves byte a byte, o que joga
% "Água", "Éter" e "Índice" para o fim da lista. Por isso a chave de ordenação
% (o nome da entrada, ou o campo sort) perde os acentos antes de ser
% guardada; o texto impresso não muda. Com a chave sem acentos usamos
% sort=word (palavra por palavra, ignorando maiúsculas): "Água" fica entre
% "abacate" e "Casa", e "Fábrica de casas" vem antes de "Fabricante".
% sort=letter daria a mesma ordem nesses casos, mas ignora espaços; def e use
% seguem a ordem de definição ou de uso, e não a alfabética.
\ExplSyntaxOn
\tl_const:Nn \c__ppgmsb_acentos_tl
{
	{Á}{A} {À}{A} {Â}{A} {Ã}{A} {Ä}{A}  {á}{a} {à}{a} {â}{a} {ã}{a} {ä}{a}
	{É}{E} {È}{E} {Ê}{E} {Ë}{E}         {é}{e} {è}{e} {ê}{e} {ë}{e}
	{Í}{I} {Ì}{I} {Î}{I} {Ï}{I}         {í}{i} {ì}{i} {î}{i} {ï}{i}
	{Ó}{O} {Ò}{O} {Ô}{O} {Õ}{O} {Ö}{O}  {ó}{o} {ò}{o} {ô}{o} {õ}{o} {ö}{o}
	{Ú}{U} {Ù}{U} {Û}{U} {Ü}{U}         {ú}{u} {ù}{u} {û}{u} {ü}{u}
	{Ç}{C} {ç}{c} {Ñ}{N} {ñ}{n}
	{\'}{} {\`}{} {\^}{} {\~}{} {\"}{} {\c}{}
}
\cs_new_protected:Npn \__ppgmsb_sem_acentos:Nnn #1#2#3
{
	\quark_if_recursion_tail_stop:n {#2}
	\tl_replace_all:Nnn #1 {#2} {#3}
	\__ppgmsb_sem_acentos:Nnn #1
}
\cs_new_protected:Npn \ppgmsb@semacentos #1
{
	\exp_last_unbraced:NNV \__ppgmsb_sem_acentos:Nnn #1
		\c__ppgmsb_acentos_tl \q_recursion_tail {} \q_recursion_stop
}
\ExplSyntaxOff
\let\ppgmsb@glsprestandardsort\glsprestandardsort
\renewcommand*{\glsprestandardsort}[3]{%
	\ppgmsb@semacentos#1%
	\ppgmsb@glsprestandardsort{#1}{#2}{#3}%
}

% Ativação: chamada por \opcoesDocumento; se o documento não chamar
% \opcoesDocumento, é feita no fim do preâmbulo.
\newif\ifppgmsb@glossariosAtivos
\newcommand*{\ppgmsb@ativarGlossarios}{%
	\ifppgmsb@glossariosAtivos\else
		\global\ppgmsb@glossariosAtivostrue
		\makenoidxglossaries
		\global\let\makeglossaries\ppgmsb@makeglossariesLegado
	\fi
}
\AtEndPreamble{\ppgmsb@ativarGlossarios}

% Lista vazia: nada é impresso (nem título, nem página em branco).
\ifdef{\GlsNoIdxMissingAction}{\renewcommand{\GlsNoIdxMissingAction}[1]{}}{}

% #1 = tipo do glossário, #2 = título em inglês, #3 = título em português
\newcommand{\ppgmsb@listaGlossario}[3]{%
	\ifcsdef{@glsref@#1}{%
		\ifthenelse{\boolean{ing}}{%
			\printnoidxglossary[type=#1,sort=word,title={#2}]%
		}{%
			\printnoidxglossary[type=#1,sort=word,title={#3}]%
		}%
	}{%
		% Nenhuma entrada usada com \gls: não imprime a lista
		\global\let\warn@noprintglossary\relax
	}%
}

\newcommand{\listaDeSimbolos}{%
	\ppgmsb@listaGlossario{symbols}{List of Symbols}{Lista de Símbolos}%
}

\newcommand{\listaDeAbreviacoes}{%
	\ppgmsb@listaGlossario{abbreviations}{List of Abbreviations}{Lista de Abreviações}%
}

% --- Compatibilidade com documentos antigos ---
% \makeglossaries exigiria um programa externo (makeglossaries/makeindex/xindy),
% que o servidor de compilação não executa: aqui ele não faz nada.
\newcommand*{\ppgmsb@makeglossariesLegado}[1][]{%
	\ppgmsb@aviso{\string\makeglossaries\space foi ignorado. A classe ja gera\MessageBreak
		as listas sem programa externo (\string\makenoidxglossaries).\MessageBreak
		Remova \string\makeglossaries\space do preambulo}%
}
\let\makeglossaries\ppgmsb@makeglossariesLegado

% \printglossary e \printglossaries passam a usar a ordenação interna.
\renewcommand*{\printglossary}[1][type=\glsdefaulttype]{%
	\ppgmsb@aviso{\string\printglossary\space foi substituido por\MessageBreak
		\string\printnoidxglossary. Use \string\listaDeSimbolos\space e\MessageBreak
		\string\listaDeAbreviacoes}%
	\printnoidxglossary[sort=word,#1]%
}
\renewcommand*{\printglossaries}{%
	\ppgmsb@aviso{\string\printglossaries\space foi substituido por\MessageBreak
		\string\printnoidxglossaries. Use \string\listaDeSimbolos\space e\MessageBreak
		\string\listaDeAbreviacoes}%
	\printnoidxglossaries
}
\makeatother




%==============================================================================
% PÁGINA DE METADADOS (COLOPHON) - VERSÃO COMPLETA
% Adicionar este código ao arquivo classPPGMSB.cls
%==============================================================================

%==============================================================================
% PACOTE PARA QR CODE
%==============================================================================
\RequirePackage{qrcode}

%==============================================================================
% CONTADORES AUTOMÁTICOS PARA METADADOS
%==============================================================================

% Novos contadores
\newcounter{TotalEquacoes}
\newcounter{TotalCapitulos}
\newcounter{TotalSecoes}
\newcounter{TotalAlgoritmos}
\newcounter{TotalCitacoes}
\newcounter{TotalQuadros}
\newcounter{TotalApendices}
\newcounter{TotalAnexos}

% Incrementos automáticos para equações
\AtBeginEnvironment{equation}{\stepcounter{TotalEquacoes}}
\AtBeginEnvironment{equation*}{\stepcounter{TotalEquacoes}}
\AtBeginEnvironment{align}{\stepcounter{TotalEquacoes}}
\AtBeginEnvironment{align*}{\stepcounter{TotalEquacoes}}
\AtBeginEnvironment{gather}{\stepcounter{TotalEquacoes}}
\AtBeginEnvironment{multline}{\stepcounter{TotalEquacoes}}
\AtBeginEnvironment{algorithm}{\stepcounter{TotalAlgoritmos}}

% Comandos para exibir contadores
\newcommand{\ContarEquacoes}{\arabic{TotalEquacoes}}
\newcommand{\ContarCapitulos}{\arabic{TotalCapitulos}}
\newcommand{\ContarSecoes}{\arabic{TotalSecoes}}
\newcommand{\ContarAlgoritmos}{\arabic{TotalAlgoritmos}}
\newcommand{\ContarApendices}{\arabic{TotalApendices}}
\newcommand{\ContarAnexos}{\arabic{TotalAnexos}}

%==============================================================================
% CONTAGEM DE REFERÊNCIAS BIBLIOGRÁFICAS
%==============================================================================
\newcounter{TotalReferencias}
\pretocmd{\bibitem}{\stepcounter{TotalReferencias}}{}{}
\newcommand{\ContarReferencias}{\arabic{TotalReferencias}}

%==============================================================================
% DATA E HORA DE COMPILAÇÃO
%==============================================================================
\newcommand{\dataCompilacao}{\the\day/\the\month/\the\year}

% Formato elegante para hora (HH:MM)
\makeatletter
\newcommand{\horaFormatada}{%
	\@tempcnta=\time
	\divide\@tempcnta by 60
	\ifnum\@tempcnta<10 0\fi
	\the\@tempcnta:%
	\@tempcntb=\@tempcnta
	\multiply\@tempcntb by 60
	\@tempcnta=\time
	\advance\@tempcnta by -\@tempcntb
	\ifnum\@tempcnta<10 0\fi
	\the\@tempcnta
}
\makeatother

%=============================================================
%==============================================================================
% INFORMAÇÕES ADICIONAIS
%==============================================================================
\makeatletter

% Licença
\def\@licenca{CC BY-NC-SA 4.0}
\def\@licencaURL{https://creativecommons.org/licenses/by-nc-sa/4.0/}
\newcommand{\Licenca}[2][https://creativecommons.org/licenses/by-nc-sa/4.0/]{%
	\def\@licencaURL{#1}%
	\def\@licenca{#2}%
}
\newcommand{\licenca}{\@licenca}
\newcommand{\licencaURL}{\@licencaURL}

% Financiamento/Bolsa
\def\@financiamento{}
\newcommand{\Financiamento}[1]{\def\@financiamento{#1}}
\newcommand{\financiamento}{\@financiamento}

% Repositório de código
\def\@repositorio{}
\newcommand{\Repositorio}[1]{\def\@repositorio{#1}}
\newcommand{\repositorio}{\@repositorio}

% Número de volumes
\def\@volumes{1}
\newcommand{\Volumes}[1]{\def\@volumes{#1}}
\newcommand{\volumes}{\@volumes}

%==============================================================================
% INFORMAÇÕES DO SISTEMA
%==============================================================================
\newcommand{\versaoClasse}{1.0.0}
\newcommand{\dataClasse}{Dezembro 2025}
\newcommand{\motorTeX}{%
\ifLuaTeX LuaLaTeX\else
\ifXeTeX XeLaTeX\else
\ifPDFTeX pdfLaTeX\else TeX\fi\fi\fi
}


% QR CODES
%==============================================================================
\newcommand{\QRTrabalho}[1][2cm]{%
	\ifx\@urltrabalho\empty
	\textit{URL não definida}%
	\else
	\qrcode[height=#1]{\@urltrabalho}%
	\fi
}

\newcommand{\QRDOI}[1][2cm]{%
	\ifx\@msbdoi\empty
	\textit{DOI não definido}%
	\else
	\qrcode[height=#1]{https://doi.org/\@msbdoi}%
	\fi
}
\makeatother
%==============================================================================
% COMANDO PRINCIPAL: PÁGINA DE METADADOS
%==============================================================================
\makeatletter
\newcommand{\paginaMetadados}{%
	\clearpage
	\thispagestyle{empty}
	\begingroup
	\footnotesize
	\setlength{\parindent}{0pt}%
	\setlength{\parskip}{0pt}%
	
	% ===== CABEÇALHO =====
	\begin{center}
		{\color{ppgmsbC1}\rule{\textwidth}{1.5pt}}\\[0.5em]
		{\Large\bfseries\scshape 
			\ifthenelse{\boolean{ing}}{Document Metadata}{Metadados do Documento}
		}\\[0.3em]
		{\color{ppgmsbC1}\rule{\textwidth}{1.5pt}}
	\end{center}
	\vspace{0.5em}
	
	% ===== TÍTULO DO TRABALHO =====
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{TITLE}{TÍTULO}}}\\[0.2em]
	\textbf{PT:} \@tituloBR\\
	\textbf{EN:} \@tituloEN\\
	\ifx\@subtituloBR\empty\else\textbf{Subtítulo PT:} \@subtituloBR\\\fi
	\ifx\@subtituloEN\empty\else\textbf{Subtítulo EN:} \@subtituloEN\\\fi
	\vspace{0.4em}
	
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	
	% ===== ADERÊNCIA ACADÊMICA =====
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{ACADEMIC ALIGNMENT}{ADERÊNCIA ACADÊMICA}}}\\[0.2em]
	\begin{tabular}{@{}p{4cm}p{10.5cm}@{}}
		\ifthenelse{\boolean{ing}}{Concentration area}{Área de concentração}: & \areaDeConcentracao \\
		\ifthenelse{\boolean{ing}}{Research line}{Linha de pesquisa}: & \linhaPesquisa \\
		\ifthenelse{\boolean{ing}}{Research group}{Grupo de pesquisa}: & \grupoPesquisa \\
	\end{tabular}
	\vspace{0.4em}
	
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	
	% ===== INSTITUIÇÃO =====
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{INSTITUTION}{INSTITUIÇÃO}}}\\[0.2em]
	\begin{tabular}{@{}p{4cm}p{10.5cm}@{}}
		\ifthenelse{\boolean{ing}}{University}{Universidade}: & \nomeUniversidade \\
		\ifthenelse{\boolean{ing}}{Department}{Departamento}: & \nomeDepartamento \\
		\ifthenelse{\boolean{ing}}{Program}{Programa}: & \nomePrograma \\
		URL: & \href{\@urluneb}{\@urluneb} | \href{\@urlmsb}{\@urlmsb} \\
		\ifthenelse{\boolean{ing}}{Rector}{Reitor(a)}: & \nomeReitor \\
		\ifthenelse{\boolean{ing}}{Vice-Rector}{Pró-Reitor(a)}: & \nomeProreitor \\
		\ifthenelse{\boolean{ing}}{Director}{Diretor(a)}: & \nomeDiretor \\
		\ifthenelse{\boolean{ing}}{Coordinator}{Coordenador(a)}: & \nomeCoordenador \\
	\end{tabular}
	\vspace{0.4em}
	
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	% ===== AUTORIA =====
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{AUTHORSHIP}{AUTORIA}}}\\[0.2em]
	\begin{tabular}{@{}p{4cm}p{10.5cm}@{}}
		\ifthenelse{\boolean{ing}}{Author}{Autor(a)}: & \autorNome \\
		Email: & \href{mailto:\@email}{\@email} \\
		\ifthenelse{\boolean{ing}}{Nationality}{Nacionalidade}: & \autorNacionalidade \\
		\ifthenelse{\boolean{ing}}{Place of birth}{Naturalidade}: & \autorMunicipio/\autorEstado \\
		\ifthenelse{\boolean{ing}}{Date of birth}{Data de nascimento}: & \dataNascimento \\
		ORCID: & \href{https://orcid.org/\@linkorcid}{\@linkorcid} \\
		Lattes: & \href{\@linklattes}{\@linklattes} \\
	\end{tabular}
	\vspace{0.4em}
	
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	
	% ===== ORIENTAÇÃO E COORIENTAÇÃO =====
	\begin{minipage}[t]{0.48\textwidth}
		{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{ADVISOR}{ORIENTAÇÃO}}}\\[0.2em]
		\textbf{\orientadorNome}\\
		\orientadorFiliacao\ (\orientadorSigla)\\
		ORCID: \href{https://orcid.org/\orientadorOrcid}{\orientadorOrcid}\\
		Lattes: \href{\orientadorLattes}{\orientadorLattes}
	\end{minipage}%
	\hfill
	\begin{minipage}[t]{0.48\textwidth}
		\ifx\@coorientadorANome\empty\else
		{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{CO-ADVISOR(S)}{COORIENTAÇÃO}}}\\[0.2em]
		\textbf{\coorientadorANome}\\
		\coorientadorAFiliacao\ (\coorientadorASigla)\\
		\ifthenelse{\boolean{ing}}{Background}{Formação}: \coorientadorAFormacao\\
		\ifthenelse{\boolean{ing}}{Research area}{Atuação}: \coorientadorAAtuacao\\
		ORCID: \href{https://orcid.org/\coorientadorAOrcid}{\coorientadorAOrcid}\\
		Lattes: \href{\coorientadorALattes}{\coorientadorALattes}\\[0.3em]
		\fi
		\ifx\@coorientadorBNome\empty\else
		\textbf{\coorientadorBNome}\\
		\coorientadorBFiliacao\ (\coorientadorBSigla)\\
		ORCID: \href{https://orcid.org/\coorientadorBOrcid}{\coorientadorBOrcid}\\
		Lattes: \href{\coorientadorBLattes}{\coorientadorBLattes}\\[0.3em]
		\fi
		\ifx\@coorientadorCNome\empty\else
		\textbf{\coorientadorCNome}\\
		\coorientadorCFiliacao\ (\coorientadorCSigla)\\
		ORCID: \href{https://orcid.org/\coorientadorCOrcid}{\coorientadorCOrcid}\\
		Lattes: \href{\coorientadorCLattes}{\coorientadorCLattes}
		\fi
	\end{minipage}
	\vspace{0.4em}
	
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	\newpage
	% ===== BANCA EXAMINADORA =====
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{EXAMINATION BOARD}{BANCA EXAMINADORA}}}\\[0.2em]
	\begin{minipage}[t]{0.48\textwidth}
		\ifx\@mANome\empty\else
		\textbf{\mANome}\\
		\mAFiliacao\ (\mASigla)\\
		\ifthenelse{\boolean{ing}}{Background}{Formação}: \mAFormacao\\
		\ifthenelse{\boolean{ing}}{Research area}{Atuação}: \mAAtuacao\\
		ORCID: \href{https://orcid.org/\mAOrcid}{\mAOrcid}\\
		Lattes: \href{\mALattes}{\mALattes}\\[0.3em]
		\fi
		\ifx\@mBNome\empty\else
		\textbf{\mBNome}\\
		\mBFiliacao\ (\mBSigla)\\
		\ifthenelse{\boolean{ing}}{Background}{Formação}: \mBFormacao\\
		\ifthenelse{\boolean{ing}}{Research area}{Atuação}: \mBAtuacao\\
		ORCID: \href{https://orcid.org/\mBOrcid}{\mBOrcid}\\
		Lattes: \href{\mBLattes}{\mBLattes}\\[0.3em]
		\fi
		\ifx\@mCNome\empty\else
		\textbf{\mCNome}\\
		\mCFiliacao\ (\mCSigla)\\
		\ifthenelse{\boolean{ing}}{Background}{Formação}: \mCFormacao\\
		\ifthenelse{\boolean{ing}}{Research area}{Atuação}: \mCAtuacao\\
		ORCID: \href{https://orcid.org/\mCOrcid}{\mCOrcid}\\
		Lattes: \href{\mCLattes}{\mCLattes}
		\fi
	\end{minipage}%
	\hfill
	\begin{minipage}[t]{0.48\textwidth}
		\ifx\@mDNome\empty\else
		\textbf{\mDNome}\\
		\mDFiliacao\ (\mDSigla)\\
		\ifthenelse{\boolean{ing}}{Background}{Formação}: \mDFormacao\\
		\ifthenelse{\boolean{ing}}{Research area}{Atuação}: \mDAtuacao\\
		ORCID: \href{https://orcid.org/\mDOrcid}{\mDOrcid}\\
		Lattes: \href{\mDLattes}{\mDLattes}\\[0.3em]
		\fi
		\ifx\@mENome\empty\else
		\textbf{\mENome}\\
		\mEFiliacao\ (\mESigla)\\
		\ifthenelse{\boolean{ing}}{Background}{Formação}: \mEFormacao\\
		\ifthenelse{\boolean{ing}}{Research area}{Atuação}: \mEAtuacao\\
		ORCID: \href{https://orcid.org/\mEOrcid}{\mEOrcid}\\
		Lattes: \href{\mELattes}{\mELattes}\\[0.3em]
		\fi
		\ifx\@mFNome\empty\else
		\textbf{\mFNome}\\
		\mFFiliacao\ (\mFSigla)\\
		\ifthenelse{\boolean{ing}}{Background}{Formação}: \mFFormacao\\
		\ifthenelse{\boolean{ing}}{Research area}{Atuação}: \mFAtuacao\\
		ORCID: \href{https://orcid.org/\mFOrcid}{\mFOrcid}\\
		Lattes: \href{\mFLattes}{\mFLattes}
		\fi
	\end{minipage}
	\vspace{0.4em}
	
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	
	% ===== PALAVRAS-CHAVE =====
	% ===== PALAVRAS-CHAVE =====
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{KEYWORDS}{PALAVRAS-CHAVE}}}\\[0.2em]
	\ifx\@palavrasChavePT\@empty\else\textbf{PT:} \@palavrasChavePT\\\fi
	\ifx\@palavrasChaveEN\@empty\else\textbf{EN:} \@palavrasChaveEN\\\fi
	\ifx\@palavrasChaveBusca\@empty\else\textbf{BUSCA:} \@palavrasChaveBusca\\\fi
	\vspace{0.4em}
	
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	% ===== INFORMAÇÕES TÉCNICAS =====
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{TECHNICAL INFORMATION}{INFORMAÇÕES TÉCNICAS}}}\\[0.2em]
	
	\begin{minipage}[t]{0.32\textwidth}
		\textbf{\ifthenelse{\boolean{ing}}{Document}{Documento}}\\[0.2em]
		\begin{tabular}{@{}p{1.5cm}r@{}}
			\ifthenelse{\boolean{ing}}{Type}{Tipo}: & \tipo \\
			\ifthenelse{\boolean{ing}}{Volumes}{Volumes}: & \volumes \\
			\ifthenelse{\boolean{ing}}{Pages}{Páginas}: & \numeroDePaginas \\
			\ifthenelse{\boolean{ing}}{Figures}{Figuras}: & \ContarFiguras \\
			\ifthenelse{\boolean{ing}}{Tables}{Tabelas}: & \ContarTabelas \\
			\ifthenelse{\boolean{ing}}{Equations}{Equações}: & \ContarEquacoes \\
			\ifthenelse{\boolean{ing}}{Algorithms}{Algoritmos}: & \ContarAlgoritmos \\
			\ifthenelse{\boolean{ing}}{References}{Referências}: & \ContarReferencias \\
		\end{tabular}
	\end{minipage}%
	\hfill
	\begin{minipage}[t]{0.32\textwidth}
		\textbf{\ifthenelse{\boolean{ing}}{Defense}{Defesa}}\\[0.2em]
		\begin{tabular}{@{}p{1.5cm}p{3cm}@{}}
			\ifthenelse{\boolean{ing}}{Date}{Data}: & \dataDefesa \\
			\ifthenelse{\boolean{ing}}{Time}{Hora}: & \horaDefesa \\
			\ifthenelse{\boolean{ing}}{Location}{Local}: & \localDefesa \\
		\end{tabular}
		\vspace{0.3em}
		
		\textbf{\ifthenelse{\boolean{ing}}{Identifiers}{Identificadores}}\\[0.2em]
		\begin{tabular}{@{}p{1.5cm}p{3cm}@{}}
			DOI: & \@msbdoi \\
			URL: & \href{\@urltrabalho}{\scriptsize\@urltrabalho} \\
		\end{tabular}
	\end{minipage}%
	\hfill
	\begin{minipage}[t]{0.32\textwidth}
		\textbf{\ifthenelse{\boolean{ing}}{Compilation}{Compilação}}\\[0.2em]
		\begin{tabular}{@{}p{1.5cm}p{2.5cm}@{}}
			\ifthenelse{\boolean{ing}}{Date}{Data}: & \dataCompilacao \\
			\ifthenelse{\boolean{ing}}{Time}{Hora}: & \horaFormatada \\
			\ifthenelse{\boolean{ing}}{Engine}{Motor}: & \motorTeX \\
			\ifthenelse{\boolean{ing}}{Class}{Classe}: & v\versaoClasse \\
		\end{tabular}
		\vspace{0.3em}
		
		\textbf{\ifthenelse{\boolean{ing}}{License}{Licença}}\\[0.2em]
		\href{\@licencaURL}{\@licenca}
	\end{minipage}
	
	% ===== FINANCIAMENTO E REPOSITÓRIO (se definidos) =====
	\ifx\@financiamento\empty\else
	\vspace{0.4em}
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{FUNDING}{FINANCIAMENTO}}}\\[0.2em]
	\@financiamento
	\fi
	
	\ifx\@repositorio\empty\else
	\vspace{0.4em}
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{SOURCE CODE REPOSITORY}{REPOSITÓRIO DE CÓDIGO}}}\\[0.2em]
	\href{\@repositorio}{\@repositorio}
	\fi
	
	% ===== ODS =====
	\ifx\ods@lista\empty\else
	\vspace{0.4em}
	{\color{ppgmsbC1}\hrule height 0.4pt}\vspace{0.4em}
	{\color{ppgmsbC1}\textbf{\ifthenelse{\boolean{ing}}{SUSTAINABLE DEVELOPMENT GOALS (SDG)}{OBJETIVOS DE DESENVOLVIMENTO SUSTENTÁVEL (ODS)}}}\\[0.3em]
	\begin{center}
		\ODSFiguras[1.2cm]
	\end{center}
	\fi
	
	% ===== RODAPÉ COM QR CODE =====
	\vfill
	
	\begin{center}
		{\color{ppgmsbC1}\rule{\textwidth}{0.4pt}}
	\end{center}
	\vspace{0.3em}
	
	\begin{minipage}[c]{0.25\textwidth}
		\centering
		\ifx\@urltrabalho\empty\else
		\QRTrabalho[2.2cm]\\[0.2em]
		{\scriptsize\ifthenelse{\boolean{ing}}{Scan to access}{Acesse o trabalho}}
		\fi
	\end{minipage}%
	\hfill
	\begin{minipage}[c]{0.70\textwidth}
		\scriptsize
		\textit{\ifthenelse{\boolean{ing}}{%
				This page contains metadata for indexing and search systems. The information presented here facilitates the identification, cataloging and retrieval of this work in academic databases and digital repositories.%
			}{%
				Esta página contém metadados para sistemas de indexação e busca. As informações aqui apresentadas facilitam a identificação, catalogação e recuperação deste trabalho em bases de dados acadêmicas e repositórios digitais.%
		}}\\[0.5em]
		
		\begin{tabular}{@{}ll@{}}
			\ifthenelse{\boolean{ing}}{Compiled on}{Compilado em}: & \dataCompilacao\ \ifthenelse{\boolean{ing}}{at}{às} \horaFormatada \\
			\ifthenelse{\boolean{ing}}{Class}{Classe}: & classPPGMSB v\versaoClasse\ (\dataClasse) \\
			\ifthenelse{\boolean{ing}}{Engine}{Motor \TeX}: & \motorTeX \\
			\ifthenelse{\boolean{ing}}{Token}{Codigo}: & \PPGMSBCertToken \\	
		\end{tabular}
	\end{minipage}
	\endgroup
}
\makeatother

%===============================================================
% FIM DA PÁGINA DE METADADOS
%===============================================================


