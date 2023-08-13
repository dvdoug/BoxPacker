const path = require('path');

module.exports = {
  entry: {
    app: './visualiser.ts'
  },
  output: {
    path: path.resolve(__dirname, '../docs/_static/js'),
    filename: 'visualiser.js'
  },
  resolve: {
    extensions: ['.ts', '.tsx', '.js']
  },
  devtool: 'source-map',
  plugins: [

  ],
  module: {
    rules: [{
      test: /\.tsx?$/,
      loader: 'ts-loader',
      exclude: /node_modules/
    }]
  }
}
