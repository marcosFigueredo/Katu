# classPPGMSB — LaTeX class for theses and dissertations (PPGMSB/UNEB)

## Description

`classPPGMSB` is a LaTeX document class for typesetting master's dissertations,
doctoral theses, and qualification exams submitted to the Graduate Program in
Biosystems Modeling and Simulation (PPGMSB) at the State University of Bahia
(UNEB), Brazil.

The class is built on top of the `memoir` class and provides:

- Automatic cover page, title page, and approval sheet
- Bilingual support (Brazilian Portuguese and English)
- Document options: `diss` (dissertation), `tese` (thesis), `qlf` (qualification),
  `diss=en`, `tese=en` (English variants)
- Metadata page (colophon) with QR codes, counters, and SDG icons
- Character-limit validation for titles and abstracts
- Pre-configured headers, footers, chapter styles, and bibliography (APA-style via `apacite`)
- Glossary/abbreviations/symbols support via `glossaries-extra`

## Author

Marcos Batista Figueredo  
<mbfigueredo@uneb.br>  
Programa de Pós-Graduação em Modelagem e Simulação de Biossistemas (PPGMSB)  
Universidade do Estado da Bahia (UNEB)  
Alagoinhas, Bahia, Brasil  
<https://ppgmsb.uneb.br>

## License

This work may be distributed and/or modified under the conditions of the
LaTeX Project Public License (LPPL), either version 1.3c or (at your option)
any later version. See <https://www.latex-project.org/lppl.txt>.

This work has the LPPL maintenance status **maintained**.  
The Current Maintainer is Marcos Batista Figueredo.

## Requirements

- LaTeX2e (pdfLaTeX, XeLaTeX, or LuaLaTeX)
- TeX Live 2020 or later (or MiKTeX equivalent)
- Required packages (loaded automatically by the class):
  `memoir`, `ifthen`, `iftex`, `etoolbox`, `environ`, `xstring`, `xcolor`,
  `lastpage`, `geometry`, `fontenc`, `lmodern`, `inputenc` (pdfLaTeX only),
  `microtype`, `graphicx`, `tikz`, `algorithm2e`, `babel`, `placeins`,
  `amsmath`, `amssymb`, `amsfonts`, `pgfplots`, `apacite`, `caption`,
  `qrcode`, `hyperref`, `indentfirst`

## Files

```
classPPGMSB.cls          — document class
brazilian.apc            — Portuguese strings for apacite (keep next to the class)
classPPGMSB-doc.pdf      — class manual
dissertacao.tex          — main template file (example document)
figuras/                 — institutional logos and SDG icons
preTextual/
    abreviacoes.tex      — abbreviations list
    simbolos.tex         — symbols list
textual/
    introducao.tex       — chapter: introduction
    revisao.tex          — chapter: literature review
    modelo.tex           — chapter: model/methods
posTextual/
    referencias.bib      — bibliography database
    apendice.tex         — appendix (optional)
    anexos.tex           — annexes (optional)
```

## Basic Usage

```latex
% For a master's dissertation (Portuguese):
\documentclass[diss]{classPPGMSB}

% For a doctoral thesis (Portuguese):
\documentclass[tese]{classPPGMSB}

% For a qualification exam:
\documentclass[qlf]{classPPGMSB}

% For a dissertation or thesis in English:
\documentclass[diss=en]{classPPGMSB}
\documentclass[tese=en]{classPPGMSB}
```

See `dissertacao.tex` for a complete working example.

## Compilation / Compilação

```sh
latexmk -pdf dissertacao.tex
```

(`-pdfxe` for XeLaTeX, `-pdflua` for LuaLaTeX; on Overleaf, just compile.)
The lists of symbols and abbreviations are built by LaTeX itself: no
`makeglossaries`, no shell-escape, no extra step.

As listas de símbolos e abreviações são geradas só pelo LaTeX: não é preciso
rodar `makeglossaries` nem habilitar shell-escape.

## Version History

- **1.0.0** (2025/12/01) — Initial public release on CTAN
