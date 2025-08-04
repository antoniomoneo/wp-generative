/**
 * @OnlyCurrentDoc
 * Este script lee datos de la hoja de cálculo activa y los sirve a una página web.
 */

// Sirve la página web cuando se accede a la URL de la aplicación.
function doGet(e) {
  return HtmlService.createTemplateFromFile('index')
      .evaluate()
      .setTitle('Visualización de Temperatura Global')
      .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

// Función para obtener los datos de la hoja de cálculo.
function getSheetData() {
  try {
    const sheetName = 'Sheet1'; // Nombre de la pestaña en tu Google Sheet.
    const startRow = 6; // La fila donde comienzan los datos.
    
    const yearColumn = 1; 
    const meanColumn = 2;
    const movAvgColumn = 3;

    const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(sheetName);
    if (!sheet) {
      throw new Error(`La hoja "${sheetName}" no fue encontrada.`);
    }

    const lastRow = sheet.getLastRow();
    const range = sheet.getRange(startRow, 1, lastRow - startRow + 1, 3);
    const values = range.getValues();

    const data = values.map(row => {
      const year = parseInt(row[yearColumn - 1], 10);
      const mean = parseFloat(row[meanColumn - 1]);
      const movAvg = parseFloat(row[movAvgColumn - 1]);

      if (!isNaN(year) && !isNaN(mean) && !isNaN(movAvg)) {
        return {
          year: year,
          mean: mean,
          movAvg: movAvg
        };
      }
      return null;
    }).filter(item => item !== null);

    return data;
  } catch (error) {
    return { error: error.message };
  }
}

// NUEVA FUNCIÓN: Devuelve la URL de la aplicación web implementada.
function getWebAppUrl() {
  return ScriptApp.getService().getUrl();
}
