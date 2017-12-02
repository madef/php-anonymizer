#!/usr/bin/env php
<?php

if (!isset($argv[1])) {
    echo "Usage : ./anonymizer.php <directory path>\n";
    echo "Usage : ./anonymizer.php <file path>\n";
    exit(1);
}
if (!file_exists($argv[1])) {
    echo "File or Directory \"${argv[1]}\" not found\n";
    exit(2);
}

if (substr($argv[1], -4) === '.php') {
    anonymizeFile($argv[1]);
    exit;
}


$contents = getDirContents($argv[1]);

foreach ($contents as $file) {
    echo shell_exec('php ./anonymizer.php '.$file);
}

function anonymizeFile($file) {
    echo 'Anonymize '.$file;
    $content = file_get_contents($file);
    $contentLines = explode("\n", $content);
    $contentLowered = strtolower($file);
    include $file;

    $namespace = '';
    preg_match('/namespace\s+([^\s]*)\s*;/', $content, $match);
    if (!empty($match[1])) {
        $namespace = '\\' . $match[1] . '\\';
    }

    preg_match_all('/class\s+(\w*)/', $content, $matchesClass);
    preg_match_all('/interface\s+(\w*)/', $content, $matchesInterface);
    if (empty($matchesClass[1]) && empty($matchesInterface)) {
        echo "No classes found\n";
        exit;
    }

    $classes = array();
    foreach ($matchesClass[1] as $class) {
        $classes[] = $class;
    }
    foreach ($matchesInterface[1] as $class) {
        $classes[] = $class;
    }

    $lineToRemove = array();
    $contentToAdd = array();

    foreach ($classes as $class) {
        $ref = new \ReflectionClass($namespace . $class); 
        foreach ($ref->getMethods() as $method) {

            $iterator = 0;
            foreach ($method->getParameters() as $parameter) {
                //$content = preg_replace('/(function.*'.$method->name.'\(.*)\$'.$parameter->name.'/', '$1$i'.$iterator, $content);
                $iterator++;
            }

            for ($i = $method->getStartLine() - 1; $i < $method->getEndLine(); $i++) {
                $lineToRemove[] = $i;
            }

            if (!$method->isConstructor()) {
                $classDeclaration = '    ';

                if ($method->isFinal()) {
                    $classDeclaration .= 'final ';
                }

                if ($method->isPublic()) {
                    $classDeclaration .= 'public ';
                } elseif ($method->isProtected()) {
                    $classDeclaration .= 'protected ';
                } else {
                    $classDeclaration .= 'private ';
                }

                if ($method->isStatic()) {
                    $classDeclaration .= 'static ';
                }

                $classDeclaration .= 'function ' . $method->getName() . '(';
                $isFirst = true;
                foreach ($method->getParameters() as $parameter) {
                    if (!$isFirst) {
                        $classDeclaration .= ', ';
                    }

                    if ($parameter->hasType()) {
                        $classDeclaration .= '\\' . $parameter->getType() . ' ';
                    }
                    $classDeclaration .= '$' . $parameter->getName();

                    if ($parameter->isOptional()) {
                        $classDeclaration .= ' = ' . var_export($parameter->getDefaultValue(), true);
                    }

                    $isFirst = false;
                }

                $classDeclaration .= ')';

                if ($ref->isInterface()) {
                    $classDeclaration .= ';';
                } else {
                    $classDeclaration .= ' {}';
                }

                $contentToAdd[] = $classDeclaration;
            }

            // $content = str_replace($method->getDocComment(), '', $content);
        }
        // $content = str_replace($ref->getDocComment(), '', $content);
    }

    foreach ($lineToRemove as $line) {
        unset($contentLines[$line]);
    }
    foreach ($contentLines as $nb => $line) {
        if (strpos($line, '    /**') === 0) {
            unset($contentLines[$nb]);
        } else if (strpos($line, '     *') === 0) {
            unset($contentLines[$nb]);
        } else if (trim($line) == '') {
            unset($contentLines[$nb]);
        } else if (strpos($line, '/**') === 0) {
            unset($contentLines[$nb]);
        } else if (strpos($line, ' *') === 0) {
            unset($contentLines[$nb]);
        }
    }

    $finalContentLines = array();
    foreach ($contentLines as $nb => $line) {
        $line = str_replace(array("\n", "\r"), '', $line);

        if ($nb == $ref->getEndLine() - 1) {
            foreach ($contentToAdd as $content) {
                $finalContentLines[] = str_replace(array("\n", "\r"), '', $content);
            }
        }
        $finalContentLines[] = $line;

    }

    // $content = preg_replace('/\n[\n]+/', "\n", $content);

    // copy($file, dirname($file).'/.'.basename($file).'.'.time());
    file_put_contents($file, implode("\n", $finalContentLines));
}

function __autoload($className) {
    $parts = explode('\\', str_replace('\\\\', '\\', $className));
    $className = array_pop($parts);
    $namespace = implode('\\', $parts);

    $namespaceDefinition = '';
    if (!empty($namespace)) {
        $namespaceDefinition = "namespace $namespace;";
    }

    if (strpos($className, 'Interface') !== false) {
        eval("$namespaceDefinition interface $className {}");
    } else {
        eval("$namespaceDefinition class $className {}");
    }
}

function getDirContents($dir, &$results = array()) {
    $files = scandir($dir);

    foreach($files as $key => $value){
        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
        if (!is_dir($path)) {
            if (substr($value, -4) === '.php' && $value !== 'registration.php') {
                $results[] = $path;
            }
        } else if($value != "." && $value != "..") {
            getDirContents($path, $results);
        }
    }

    return $results;
}
