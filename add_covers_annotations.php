<?php

/**
 * Script to add @covers annotations to PHPUnit tests
 * This addresses the "risky test" warnings by adding proper coverage metadata
 */

// Define the mapping of test classes to their target classes
$testClassMappings = [
    'SimpleVMTest' => 'VmManagement\\SimpleVM',
    'VMManagerTest' => 'VmManagement\\VMManager',
    'TestTest' => 'VmManagement\\SimpleVM', // Just a dummy test, can cover SimpleVM
    'LibvirtConnectionExceptionTest' => 'VmManagement\\Exceptions\\LibvirtConnectionException',
    'NetworkExceptionTest' => 'VmManagement\\Exceptions\\NetworkException',
    'VMCreationExceptionTest' => 'VmManagement\\Exceptions\\VMCreationException',
    'ValidationExceptionTest' => 'VmManagement\\Exceptions\\ValidationException',
    'EndToEndTest' => 'VmManagement\\VMManager',
    'NetworkIsolationTest' => 'VmManagement\\VMManager',
    'ErrorHandlingTest' => 'VmManagement\\VMManager',
    'WebInterfaceLibvirtTest' => 'VmManagement\\VMManager',
];

// Method-specific covers for complex test methods
$methodSpecificCovers = [
    'testSimpleVMCanBeInstantiated' => 'VmManagement\\SimpleVM::__construct',
    'testSimpleVMPropertiesCanBeSet' => 'VmManagement\\SimpleVM::__construct',
    'testDefaultValuesAreSetCorrectly' => 'VmManagement\\SimpleVM::__construct',
    'testVlanIdMappingForAllUsers' => 'VmManagement\\SimpleVM::getVlanIdForUser',
    'testVlanIdDefaultsTo100ForUnknownUser' => 'VmManagement\\SimpleVM::getVlanIdForUser',
    'testToArrayReturnsCorrectStructure' => 'VmManagement\\SimpleVM::toArray',
    'testPropertiesCanBeModifiedAfterInstantiation' => 'VmManagement\\SimpleVM::__construct',
    'testUpdateStatus' => 'VmManagement\\SimpleVM::updateStatus',
    'testSetSSHInfo' => 'VmManagement\\SimpleVM::setSSHInfo',
];

function addCoversAnnotations($filePath, $targetClass, $methodSpecificCovers) {
    $content = file_get_contents($filePath);

    // Find all test methods
    preg_match_all('/(\s+)public function (test\w+)\([^)]*\):\s*void\s*\{/', $content, $matches, PREG_SET_ORDER);

    $updatedContent = $content;

    foreach ($matches as $match) {
        $indentation = $match[1];
        $methodName = $match[2];
        $fullMatch = $match[0];

        // Check if this method already has @covers
        if (strpos($updatedContent, "@covers") !== false) {
            $beforeMethod = substr($updatedContent, 0, strpos($updatedContent, $fullMatch));
            if (strpos($beforeMethod, "@covers") !== false) {
                $lastCoversPos = strrpos($beforeMethod, "@covers");
                $afterLastCovers = substr($beforeMethod, $lastCoversPos);
                if (strpos($afterLastCovers, "public function") === false) {
                    // Already has @covers for this method
                    continue;
                }
            }
        }

        // Determine which class/method to cover
        $coversTarget = $methodSpecificCovers[$methodName] ?? $targetClass;

        // Create the @covers annotation
        $coversAnnotation = $indentation . "/**\n" .
                           $indentation . " * @covers \\" . $coversTarget . "\n" .
                           $indentation . " */\n";

        // Replace the method declaration
        $newMethodDeclaration = $coversAnnotation . $fullMatch;
        $updatedContent = str_replace($fullMatch, $newMethodDeclaration, $updatedContent);
    }

    file_put_contents($filePath, $updatedContent);
    echo "Updated: $filePath\n";
}

// Find all test files
$testDirs = [
    __DIR__ . '/tests/Unit',
    __DIR__ . '/tests/Integration'
];

foreach ($testDirs as $testDir) {
    if (!is_dir($testDir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testDir)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getPathname();
            $fileName = $file->getBasename('.php');

            // Determine target class
            $targetClass = $testClassMappings[$fileName] ?? 'VmManagement\\VMManager';

            echo "Processing: $filePath -> $targetClass\n";
            addCoversAnnotations($filePath, $targetClass, $methodSpecificCovers);
        }
    }
}

echo "Done adding @covers annotations to all test files.\n";
