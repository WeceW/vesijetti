# Tampereen vesijettivuokrauksen vuokrausjärjestelmän hakuwidget

Tämä hakuwidget mahdollistaa vuokrattavien laitteiden hakemisen ja lisäämisen
Pinpoint Booking Systemiin ja WooCommercen ostoskoriin.

Testattu toimivaksi Chromella (uusin versio), Firefoxilla (uusin versio),
Edgellä (uusin versio), Safarilla (uusin versio) ja Internet Explorer 11:llä.

JavaScript-koodit täytyy "kääntää" ennen käyttöä. Pikaiset ohjeet:
1. Asenna [Node.js](https://nodejs.org/) (ja sen mukana NPM)
2. Aja `npm install`

Tämän jälkeen JS-koodin voi kääntää. Mikäli halutaan kääntää testausversio, se
pitää tehdä jokaisen sellaisen muutoksen jälkeen, joka halutaan nähdä sivuilla.

Kääntäminen testiympäristöön:
```bash
$ npm run build
```

Kääntäminen tuotantoympärisöön (minifioi koodin):
```bash
$ npm run build-dist
```

Kääntäminen mahdollistaa ES2015-2017-ominaisuuksien käytön.
Ks. esim. https://babeljs.io/learn-es2015/
