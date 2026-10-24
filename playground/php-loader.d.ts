declare module "boxpacker-php-loader" {
    const dependencyFilename: string;
    const dependenciesTotalSize: number;
    function init(...args: unknown[]): unknown;
    export default function PHPLoader(...args: unknown[]): unknown;
    export { dependencyFilename, dependenciesTotalSize, init };
}
